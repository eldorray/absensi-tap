<?php

namespace App\Enums;

enum StatusHari: string
{
    case BukanHariKerja = 'bukan_hari_kerja';
    case Libur = 'libur';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Alfa = 'alfa';
    case Belum = 'belum';

    /**
     * Label singkat untuk tabel rekap dan export CSV.
     */
    public function label(): string
    {
        return match ($this) {
            self::BukanHariKerja => '-',
            self::Libur => 'Libur',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Alfa => 'Alfa',
            self::Belum => 'Belum',
        };
    }
}
