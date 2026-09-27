<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatAbsensi;
use App\Enums\StatusAbsensi;
use App\Enums\TipeTap;
use App\Http\Requests\CatatAbsensiRequest;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\PengaturanAbsensi;
use App\Models\Pengumuman;
use App\Models\Perangkat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AbsensiController extends Controller
{
    /**
     * Halaman tap milik guru: jadwal dan status hari ini.
     */
    public function index(Request $request): Response
    {
        $guru = $request->user();

        $jadwal = JadwalKerja::hariUntukGuru((int) $guru->id, now()->dayOfWeek);
        $pengaturan = PengaturanAbsensi::current();

        $uuidPerangkat = $request->cookie('perangkat_uuid');

        $hariIni = Absensi::query()
            ->with(['masukAttempt', 'pulangAttempt'])
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->first();

        return Inertia::render('Dashboard', [
            'jadwal' => $jadwal === null ? null : [
                'jam_masuk' => substr($jadwal->jam_masuk, 0, 5),
                'jam_pulang' => substr($jadwal->jam_pulang, 0, 5),
                'toleransi_menit' => $pengaturan->toleransi_menit,
                'buka_masuk' => today()->setTimeFromTimeString($jadwal->jam_masuk)
                    ->subMinutes($pengaturan->buka_masuk_menit)->format('H:i'),
                'tutup_masuk' => today()->setTimeFromTimeString($jadwal->jam_masuk)
                    ->addMinutes($pengaturan->tutup_masuk_menit)->format('H:i'),
                'buka_pulang' => today()->setTimeFromTimeString($jadwal->jam_pulang)
                    ->subMinutes($pengaturan->buka_pulang_menit)->format('H:i'),
                'is_hari_kerja' => $jadwal->is_hari_kerja,
            ],
            'hariIni' => $hariIni === null ? null : [
                'status' => $hariIni->status?->value,
                'jam_masuk' => $hariIni->masukAttempt?->created_at?->format('H:i'),
                'jam_pulang' => $hariIni->pulangAttempt?->created_at?->format('H:i'),
                'pulang_cepat' => $hariIni->pulang_cepat,
                'terverifikasi' => $hariIni->masukAttempt->terverifikasi ?? false,
            ],
            // Jam server, supaya tombol absen di HP mengikuti jendela yang sama
            // dengan yang dinilai server walau jam HP meleset.
            'waktuServer' => now()->toIso8601String(),
            'libur' => HariLibur::query()->whereDate('tanggal', today())->value('nama'),
            'pengumumans' => Pengumuman::query()
                ->where('is_active', true)
                ->latest()
                ->latest('id')
                ->limit(5)
                ->get(['id', 'judul', 'isi', 'created_at'])
                ->map(fn (Pengumuman $pengumuman): array => [
                    'id' => $pengumuman->id,
                    'judul' => $pengumuman->judul,
                    'isi' => $pengumuman->isi,
                    'dibuat' => $pengumuman->created_at?->toIso8601String(),
                ]),
            'lokasis' => Lokasi::query()
                ->aktifUntukKantor($guru->kantor_id)
                ->orderBy('nama')
                ->get(['id', 'nama', 'latitude', 'longitude', 'radius_meter']),
            'punyaPasskey' => $guru->hasPasskeysEnabled(),
            'perangkatUuidTersimpan' => $uuidPerangkat,
            'statusPerangkat' => $uuidPerangkat === null ? null : Perangkat::query()
                ->where('user_id', $guru->id)
                ->where('uuid', $uuidPerangkat)
                ->value('status'),
        ]);
    }

    /**
     * Terima satu tap. Semua gerbang penolak ada di CatatAbsensi.
     */
    public function store(CatatAbsensiRequest $request, CatatAbsensi $catat): RedirectResponse
    {
        $absensi = $catat(
            $request->user(),
            TipeTap::from($request->string('tipe')->toString()),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (int) round((float) $request->input('accuracy')),
            $request->string('device_uuid')->toString(),
            $this->passkeyTerverifikasi($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->pesanTercatat($absensi, $request->string('tipe')->toString())]);

        return to_route('dashboard');
    }

    /**
     * Pesan sukses yang langsung menjawab "tercatat jam berapa, telat atau
     * tidak", supaya guru tidak perlu membuka riwayat untuk memastikan.
     */
    private function pesanTercatat(Absensi $absensi, string $tipe): string
    {
        $jam = now()->format('H.i');

        if ($tipe === TipeTap::Pulang->value) {
            return "Absen pulang tercatat pukul {$jam}.".($absensi->pulang_cepat ? ' Tercatat pulang cepat.' : '');
        }

        return "Absen masuk tercatat pukul {$jam}.".($absensi->status === StatusAbsensi::Terlambat
            ? " Terlambat {$absensi->menit_terlambat} menit."
            : ' Tepat waktu.');
    }

    /**
     * Apakah ada verifikasi biometrik yang masih segar? Penanda sekali pakai:
     * di-pull, bukan di-get, supaya satu verifikasi tidak bisa dipakai untuk
     * dua tap.
     */
    private function passkeyTerverifikasi(Request $request): bool
    {
        $ditandai = $request->session()->pull(CatatAbsensi::KEY_VERIFIKASI);

        if (! is_string($ditandai)) {
            return false;
        }

        return Carbon::parse($ditandai)->diffInSeconds(now()) <= CatatAbsensi::UMUR_VERIFIKASI_DETIK;
    }
}
