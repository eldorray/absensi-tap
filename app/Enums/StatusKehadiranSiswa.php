<?php

namespace App\Enums;

enum StatusKehadiranSiswa: string
{
    case Hadir = 'hadir';
    case Sakit = 'sakit';
    case Izin = 'izin';
    case Alpa = 'alpa';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
