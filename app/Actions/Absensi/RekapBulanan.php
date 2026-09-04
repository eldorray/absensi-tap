<?php

namespace App\Actions\Absensi;

use App\Enums\HasilTap;
use App\Enums\Role;
use App\Enums\StatusIzin;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\User;
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
     *         ringkasan: array<string, int>
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

        $jadwals = JadwalKerja::query()->get()->keyBy('day_of_week');
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

            foreach ($tanggals as $tanggal) {
                $kunci = $guru->id.'|'.$tanggal;
                $absensi = $absensis->get($kunci);
                $jadwal = $jadwals->get(Carbon::parse($tanggal)->dayOfWeek);
                $status = StatusHarian::resolve(
                    $jadwal === null ? true : $jadwal->is_hari_kerja,
                    $liburs->has($tanggal),
                    $izins[$kunci] ?? null,
                    $absensi?->status?->value,
                    Carbon::parse($tanggal)->isBefore(today()),
                );
                $anomali = [];
                $attempt = $absensi?->masukAttempt;

                if ($attempt !== null && ! $attempt->terverifikasi) {
                    $anomali[] = 'tanpa_biometrik';
                }
                if ($attempt !== null && in_array($this->kunciKoordinat($attempt), $kembar, true)) {
                    $anomali[] = 'koordinat_kembar';
                }
                if ($absensi !== null && $absensi->pulang_cepat) {
                    $anomali[] = 'pulang_cepat';
                }
                if ($absensi !== null && $absensi->pulang_attempt_id === null) {
                    $anomali[] = 'belum_tap_pulang';
                }

                $hari[] = [
                    'tanggal' => $tanggal,
                    'status' => $status->value,
                    'label' => $status->label(),
                    'anomali' => $anomali,
                ];
                $ringkasan[$status->value] = ($ringkasan[$status->value] ?? 0) + 1;
            }

            $baris[] = [
                'user_id' => $guru->id,
                'nama' => $guru->name,
                'nip' => $guru->nip,
                'hari' => $hari,
                'ringkasan' => $ringkasan,
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

    private function kunciKoordinat(AbsensiAttempt $attempt): string
    {
        return $attempt->created_at?->toDateString().'|'.$attempt->latitude.'|'.$attempt->longitude;
    }
}
