<?php

namespace App\Enums;

enum StatusSesiAbsensiSiswa: string
{
    case BelumDiperiksa = 'belum_diperiksa';
    case Draft = 'draft';
    case Final = 'final';
    case Dikoreksi = 'dikoreksi';
}
