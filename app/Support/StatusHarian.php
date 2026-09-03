<?php

namespace App\Support;

use App\Enums\StatusHari;

final class StatusHarian
{
    /**
     * Tentukan status satu guru pada satu tanggal.
     *
     * Cabang dievaluasi berurutan dan berhenti di kecocokan pertama. Urutan ini
     * yang menentukan benar-tidaknya seluruh rekap, jadi jangan diubah tanpa
     * memperbarui test-nya.
     *
     * @param  string|null  $tipeIzinDisetujui  'izin', 'sakit', atau 'cuti'
     * @param  string|null  $statusAbsensi  'hadir' atau 'terlambat'
     */
    public static function resolve(
        bool $isHariKerja,
        bool $isLibur,
        ?string $tipeIzinDisetujui,
        ?string $statusAbsensi,
        bool $tanggalSudahLewat,
    ): StatusHari {
        if (! $isHariKerja) {
            return StatusHari::BukanHariKerja;
        }

        if ($isLibur) {
            return StatusHari::Libur;
        }

        if ($tipeIzinDisetujui !== null) {
            return StatusHari::from($tipeIzinDisetujui);
        }

        if ($statusAbsensi !== null) {
            return StatusHari::from($statusAbsensi);
        }

        return $tanggalSudahLewat ? StatusHari::Alfa : StatusHari::Belum;
    }
}
