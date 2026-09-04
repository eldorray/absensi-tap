<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatAbsensi;
use App\Enums\TipeTap;
use App\Http\Requests\CatatAbsensiRequest;
use App\Models\Absensi;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
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

        $jadwal = JadwalKerja::query()->where('day_of_week', now()->dayOfWeek)->first();

        $hariIni = Absensi::query()
            ->with(['masukAttempt', 'pulangAttempt'])
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->first();

        return Inertia::render('Dashboard', [
            'jadwal' => $jadwal === null ? null : [
                'jam_masuk' => substr($jadwal->jam_masuk, 0, 5),
                'jam_pulang' => substr($jadwal->jam_pulang, 0, 5),
                'toleransi_menit' => $jadwal->toleransi_menit,
                'is_hari_kerja' => $jadwal->is_hari_kerja,
            ],
            'hariIni' => $hariIni === null ? null : [
                'status' => $hariIni->status?->value,
                'jam_masuk' => $hariIni->masukAttempt?->created_at?->format('H:i'),
                'jam_pulang' => $hariIni->pulangAttempt?->created_at?->format('H:i'),
                'pulang_cepat' => $hariIni->pulang_cepat,
                'terverifikasi' => $hariIni->masukAttempt->terverifikasi ?? false,
            ],
            'namaLokasi' => Lokasi::query()->where('is_active', true)->value('nama'),
            'punyaPasskey' => $guru->hasPasskeysEnabled(),
            'perangkatUuidTersimpan' => $request->cookie('perangkat_uuid'),
        ]);
    }

    /**
     * Terima satu tap. Semua gerbang penolak ada di CatatAbsensi.
     */
    public function store(CatatAbsensiRequest $request, CatatAbsensi $catat): RedirectResponse
    {
        $catat(
            $request->user(),
            TipeTap::from($request->string('tipe')->toString()),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (int) round((float) $request->input('accuracy')),
            $request->string('device_uuid')->toString(),
            $this->passkeyTerverifikasi($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Absen tercatat.']);

        return to_route('dashboard');
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
