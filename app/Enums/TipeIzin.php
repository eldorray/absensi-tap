<?php

namespace App\Enums;

/**
 * Nilai sengaja sepadan dengan StatusHari, alasan sama seperti StatusAbsensi.
 */
enum TipeIzin: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
}
