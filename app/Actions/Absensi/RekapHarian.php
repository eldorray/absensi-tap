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
use App\Support\AnomaliAbsensi;
use App\Support\StatusHarian;
use Illuminate\Support\Carbon;

/**
 * Rekap satu tanggal untuk seluruh guru.
 *
 * Bedanya dengan RekapBulanan: di sini yang dicari bukan pola sebulan, tapi
 * siapa yang belum absen hari ini dan jam berapa masing-masing masuk. Karena
 * itu jam, jarak, dan lokasi tapnya ikut dibawa.
 */
class RekapHarian
{
    /**
     * @return array{
     *     tanggal: string,
     *     ringkasan: array<string, int>,
     *     baris: list<array{
     *         user_id: int,
     *         nama: string,
     *         nip: string|null,
     *         status: string,
     *         label: string,
     *         jadwal: string|null,
     *         jam_masuk: string|null,
     *         jam_pulang: string|null,
     *         jarak_meter: int|null,
     *         lokasi: string|null,
     *         terverifikasi: bool,
     *         anomali: list<string>
     *     }>
     * }
     */
    public function __invoke(Carbon $tanggal): array
    {
        $tanggalString = $tanggal->toDateString();
        $isLibur = HariLibur::query()->whereDate('tanggal', $tanggal)->exists();
        $kembar = $this->koordinatKembar($tanggal);
        $sudahLewat = $tanggal->copy()->startOfDay()->isBefore(today());

        $gurus = User::query()
            ->where('role', Role::Guru)
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);

        $absensis = Absensi::query()
            ->with(['masukAttempt.lokasi', 'pulangAttempt'])
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('user_id');

        $izins = $this->petaIzin($tanggal);

        // Sekali ambil: jadwal default sekolah lalu jadwal milik guru yang
        // menimpanya pada hari itu.
        $semuaJadwal = JadwalKerja::query()->where('day_of_week', $tanggal->dayOfWeek)->get();
        $jadwalDefault = $semuaJadwal->firstWhere('user_id', null);
        $jadwalGuru = $semuaJadwal->whereNotNull('user_id')->keyBy('user_id');

        $ringkasan = [];
        $baris = [];

        foreach ($gurus as $guru) {
            $absensi = $absensis->get($guru->id);
            $jadwal = $jadwalGuru->get($guru->id) ?? $jadwalDefault;
            $status = StatusHarian::resolve(
                $jadwal === null ? true : $jadwal->is_hari_kerja,
                $isLibur,
                $izins[$guru->id] ?? null,
                $absensi?->status?->value,
                $sudahLewat,
            );
            $attempt = $absensi?->masukAttempt;

            $baris[] = [
                'user_id' => (int) $guru->id,
                'nama' => $guru->name,
                'nip' => $guru->nip,
                'status' => $status->value,
                'label' => $status->label(),
                'jadwal' => $jadwal === null || ! $jadwal->is_hari_kerja
                    ? null
                    : substr($jadwal->jam_masuk, 0, 5).'-'.substr($jadwal->jam_pulang, 0, 5),
                'jam_masuk' => $attempt?->created_at?->format('H:i'),
                'jam_pulang' => $absensi?->pulangAttempt?->created_at?->format('H:i'),
                'jarak_meter' => $attempt?->jarak_meter,
                'lokasi' => $attempt?->lokasi?->nama,
                'terverifikasi' => $attempt !== null && $attempt->terverifikasi,
                'anomali' => AnomaliAbsensi::untuk($absensi, $kembar),
            ];

            $ringkasan[$status->value] = ($ringkasan[$status->value] ?? 0) + 1;
        }

        return ['tanggal' => $tanggalString, 'ringkasan' => $ringkasan, 'baris' => $baris];
    }

    /**
     * Tipe izin yang disetujui pada tanggal itu, dikunci user_id.
     *
     * @return array<int, string>
     */
    private function petaIzin(Carbon $tanggal): array
    {
        return Izin::query()
            ->where('status', StatusIzin::Disetujui)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->get()
            ->mapWithKeys(fn (Izin $izin): array => [$izin->user_id => $izin->tipe->value])
            ->all();
    }

    /**
     * Koordinat yang pada tanggal itu dipakai lebih dari satu guru.
     *
     * @return list<string>
     */
    private function koordinatKembar(Carbon $tanggal): array
    {
        return array_values(AbsensiAttempt::query()
            ->selectRaw('date(created_at) as tanggal, latitude, longitude')
            ->where('hasil', HasilTap::Diterima)
            ->whereBetween('created_at', [$tanggal->copy()->startOfDay(), $tanggal->copy()->endOfDay()])
            ->groupBy('tanggal', 'latitude', 'longitude')
            ->havingRaw('count(distinct user_id) > 1')
            ->get()
            ->map(fn (AbsensiAttempt $baris): string => (string) $baris->getAttribute('tanggal').'|'.(float) $baris->latitude.'|'.(float) $baris->longitude)
            ->all());
    }
}
