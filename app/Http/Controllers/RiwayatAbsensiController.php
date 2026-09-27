<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\RekapBulanan;
use App\Enums\StatusHari;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatAbsensiController extends Controller
{
    /**
     * Batas bawah navigasi bulan, sama dengan batas rekap admin.
     */
    private const BULAN_PALING_AWAL = '2020-01';

    /**
     * Riwayat satu bulan milik pengguna yang sedang login.
     *
     * Status per hari diambil dari RekapBulanan, bukan dari tabel absensis
     * saja: hari alfa, izin, dan libur tidak punya baris absensi, padahal
     * justru itu yang paling perlu dilihat guru. Dengan sumber yang sama,
     * angka di sini selalu cocok dengan rekap yang dilihat admin.
     */
    public function index(Request $request, RekapBulanan $rekapBulanan): Response
    {
        $data = $request->validate([
            'bulan' => [
                'nullable',
                'date_format:Y-m',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Perbandingan string aman karena formatnya sudah Y-m.
                    if ((string) $value > Carbon::today()->format('Y-m')) {
                        $fail('Riwayat bulan yang belum berjalan belum tersedia.');
                    }

                    if ((string) $value < self::BULAN_PALING_AWAL) {
                        $fail('Riwayat paling awal Januari 2020.');
                    }
                },
            ],
        ]);

        $user = $request->user();
        // Carbon::today(), bukan helper today(): aplikasi memakai CarbonImmutable
        // sebagai kelas tanggal bawaan, sedangkan RekapBulanan menerima Carbon.
        $mulai = isset($data['bulan'])
            ? Carbon::createFromFormat('!Y-m', $data['bulan'])->startOfMonth()
            : Carbon::today()->startOfMonth();
        // Bulan berjalan berhenti di hari ini: hari yang belum tiba bukan riwayat.
        $selesai = $mulai->copy()->endOfMonth()->min(Carbon::today());
        $baris = $rekapBulanan->periode($mulai, $selesai, $user->id)['baris'][0];

        $absensis = Absensi::query()
            ->with(['masukAttempt', 'pulangAttempt'])
            ->where('user_id', $user->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get()
            ->keyBy(fn (Absensi $absensi): string => $absensi->tanggal->toDateString());

        // "Tanpa biometrik" hanya bermakna bagi yang sudah memasang passkey;
        // tanpa passkey semua tap memang tanpa biometrik, jadi tanda itu cuma
        // menakut-nakuti.
        $punyaPasskey = $user->hasPasskeysEnabled();
        $hari = [];

        foreach (array_reverse($baris['hari']) as $status) {
            $absensi = $absensis->get($status['tanggal']);

            // Hari non-kerja tanpa tap tidak ada yang perlu dilihat.
            if ($status['status'] === StatusHari::BukanHariKerja->value && $absensi === null) {
                continue;
            }

            $hari[] = [
                'tanggal' => $status['tanggal'],
                'status' => $status['status'],
                'jam_masuk' => $absensi?->masukAttempt?->created_at?->format('H:i'),
                'jam_pulang' => $absensi?->pulangAttempt?->created_at?->format('H:i'),
                'menit_terlambat' => $status['status'] === StatusHari::Terlambat->value
                    ? ($absensi->menit_terlambat ?? 0)
                    : 0,
                'pulang_cepat' => $absensi->pulang_cepat ?? false,
                'tanpa_biometrik' => $punyaPasskey
                    && $absensi?->masukAttempt !== null
                    && ! $absensi->masukAttempt->terverifikasi,
            ];
        }

        $ringkasan = $baris['ringkasan'];

        return Inertia::render('riwayat/Index', [
            'bulan' => [
                'nilai' => $mulai->format('Y-m'),
                'label' => $mulai->translatedFormat('F Y'),
                'sebelumnya' => $mulai->format('Y-m') > self::BULAN_PALING_AWAL
                    ? $mulai->copy()->subMonth()->format('Y-m')
                    : null,
                'berikutnya' => $mulai->format('Y-m') < Carbon::today()->format('Y-m')
                    ? $mulai->copy()->addMonth()->format('Y-m')
                    : null,
            ],
            'ringkasan' => [
                'hari_efektif' => $baris['hari_efektif'],
                'hadir' => $ringkasan[StatusHari::Hadir->value] ?? 0,
                'terlambat' => $baris['terlambat'],
                'menit_terlambat' => $baris['menit_terlambat'],
                'izin' => $ringkasan[StatusHari::Izin->value] ?? 0,
                'sakit' => $ringkasan[StatusHari::Sakit->value] ?? 0,
                'cuti' => $ringkasan[StatusHari::Cuti->value] ?? 0,
                'alfa' => $ringkasan[StatusHari::Alfa->value] ?? 0,
                'persentase' => $baris['persentase'],
            ],
            'hari' => $hari,
        ]);
    }
}
