<?php

namespace App\Enums;

enum StatusIzin: string
{
    case Pending = 'pending';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
}
