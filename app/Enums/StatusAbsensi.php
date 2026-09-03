<?php

namespace App\Enums;

/**
 * Nilai sengaja sepadan dengan StatusHari agar StatusHarian::resolve()
 * bisa memetakannya langsung lewat StatusHari::from().
 */
enum StatusAbsensi: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
}
