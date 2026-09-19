<?php

namespace App\Actions\Absensi;

use App\Enums\HasilTap;
use App\Enums\Role;
use App\Enums\StatusHari;
use App\Enums\StatusIzin;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\User;
use App\Support\AnomaliAbsensi;
use App\Support\StatusHarian;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class RekapBulanan
{
    /**
     * @return array{
     *     tanggals: list<string>,
     *     baris: list<array{
     *         user_id: int,
     *         nama: string,
     *         nip: string|null,
     *         hari: list<array{tanggal: string, status: string, label: string, anomali: list<string>}>,
     *         ringkasan: array<string, int>,
     *         hari_efektif: int,
     *         kehadiran: int,
     *         masuk: int,
     *         pulang: int,
     *         terlambat: int,
     *         menit_terlambat: int,
     *         persentase: float
     *     }>
     * }
     */
    public function __invoke(int $tahun, int $bulan, ?int $userId = null): array
    {
        $mulai = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();
        $tanggals = [];

        for ($hari = $mulai->copy(); $hari->lessThanOrEqualTo($selesai); $hari->addDay()) {
            $tanggals[] = $hari->toDateString();
        }

        // Sekali ambil untuk seluruh bulan: jadwal default sekolah, lalu jadwal
        // milik guru yang menimpanya per hari.
        $semuaJadwal = JadwalKerja::query()->get();
        $jadwalDefault = $semuaJadwal->whereNull('user_id')->keyBy('day_of_week');
        $jadwalGuru = $semuaJadwal
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(fn ($baris) => $baris->keyBy('day_of_week'));
        $liburs = HariLibur::query()
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get()
            ->keyBy(fn (HariLibur $libur): string => $libur->tanggal->toDateString());
        $gurus = User::query()
            ->where('role', Role::Guru)
            ->when($userId !== null, fn ($query) => $query->where('id', $userId))
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);
        $absensis = Absensi::query()
            ->with('masukAttempt')
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->get()
            ->keyBy(fn (Absensi $absensi): string => $absensi->user_id.'|'.$absensi->tanggal->toDateString());
        $izins = $this->petaIzin($mulai, $selesai, $userId);
        $kembar = $this->koordinatKembar($mulai, $selesai);
        $baris = [];

        foreach ($gurus as $guru) {
            $hari = [];
            $ringkasan = [];
            $hariEfektif = 0;
            $masuk = 0;
            $pulang = 0;
            $menitTerlambat = 0;

            foreach ($tanggals as $tanggal) {
                $kunci = $guru->id.'|'.$tanggal;
                $absensi = $absensis->get($kunci);
                $dayOfWeek = Carbon::parse($tanggal)->dayOfWeek;
                $jadwal = $jadwalGuru->get($guru->id)?->get($dayOfWeek)
                    ?? $jadwalDefault->get($dayOfWeek);
                $status = StatusHarian::resolve(
                    $jadwal === null ? true : $jadwal->is_hari_kerja,
                    $liburs->has($tanggal),
                    $izins[$kunci] ?? null,
                    $absensi?->status?->value,
                    Carbon::parse($tanggal)->isBefore(today()),
                );
                $anomali = AnomaliAbsensi::untuk($absensi, $kembar);

                $hari[] = [
                    'tanggal' => $tanggal,
                    'status' => $status->value,
                    'label' => $status->label(),
                    'anomali' => $anomali,
                ];
                $ringkasan[$status->value] = ($ringkasan[$status->value] ?? 0) + 1;

                // Hari efektif = hari yang seharusnya dia masuk: bukan hari
                // libur, bukan hari non-kerja menurut jadwalnya sendiri, dan
                // bukan hari yang izinnya disetujui.
                if (! in_array($status, [StatusHari::BukanHariKerja, StatusHari::Libur, StatusHari::Izin, StatusHari::Sakit, StatusHari::Cuti], true)) {
                    $hariEfektif++;
                }

                if ($absensi?->masuk_attempt_id !== null) {
                    $masuk++;
                }

                if ($absensi?->pulang_attempt_id !== null) {
                    $pulang++;
                }

                if ($status === StatusHari::Terlambat && $absensi?->masukAttempt?->created_at !== null && $jadwal !== null) {
                    $jamMasuk = Carbon::parse($tanggal.' '.$jadwal->jam_masuk);
                    $menitTerlambat += (int) $jamMasuk->diffInMinutes($absensi->masukAttempt->created_at);
                }
            }

            $terlambat = $ringkasan[StatusHari::Terlambat->value] ?? 0;
            $kehadiran = ($ringkasan[StatusHari::Hadir->value] ?? 0) + $terlambat;

            $baris[] = [
                'user_id' => $guru->id,
                'nama' => $guru->name,
                'nip' => $guru->nip,
                'hari' => $hari,
                'ringkasan' => $ringkasan,
                'hari_efektif' => $hariEfektif,
                'kehadiran' => $kehadiran,
                'masuk' => $masuk,
                'pulang' => $pulang,
                'terlambat' => $terlambat,
                'menit_terlambat' => $menitTerlambat,
                // Dibulatkan dua angka: dipakai apa adanya di laporan cetak.
                'persentase' => $hariEfektif === 0
                    ? 0.0
                    : round($kehadiran / $hariEfektif * 100, 2),
            ];
        }

        return ['tanggals' => $tanggals, 'baris' => $baris];
    }

    /** @return array<string, string> */
    private function petaIzin(Carbon $mulai, Carbon $selesai, ?int $userId): array
    {
        $peta = [];
        $izins = Izin::query()
            ->where('status', StatusIzin::Disetujui)
            ->where('tanggal_mulai', '<=', $selesai)
            ->where('tanggal_selesai', '>=', $mulai)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->get();

        foreach ($izins as $izin) {
            $hari = $izin->tanggal_mulai->greaterThan($mulai)
                ? $izin->tanggal_mulai->copy()
                : $mulai->copy();
            $akhir = $izin->tanggal_selesai->lessThan($selesai)
                ? $izin->tanggal_selesai->copy()
                : $selesai->copy();

            foreach (CarbonPeriod::create($hari, $akhir) as $tanggal) {
                $peta[$izin->user_id.'|'.$tanggal->toDateString()] = $izin->tipe->value;
            }
        }

        return $peta;
    }

    /** @return list<string> */
    private function koordinatKembar(Carbon $mulai, Carbon $selesai): array
    {
        return array_values(AbsensiAttempt::query()
            ->selectRaw('date(created_at) as tanggal, latitude, longitude')
            ->where('hasil', HasilTap::Diterima)
            ->whereBetween('created_at', [$mulai->copy()->startOfDay(), $selesai->copy()->endOfDay()])
            ->groupBy('tanggal', 'latitude', 'longitude')
            ->havingRaw('count(distinct user_id) > 1')
            ->get()
            ->map(fn (AbsensiAttempt $baris): string => (string) $baris->getAttribute('tanggal').'|'.(float) $baris->latitude.'|'.(float) $baris->longitude)
            ->all());
    }
}
