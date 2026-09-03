<?php

namespace App\Enums;

enum HasilTap: string
{
    case Diterima = 'diterima';
    case LuarRadius = 'luar_radius';
    case AkurasiBuruk = 'akurasi_buruk';
    case PerangkatAsing = 'perangkat_asing';
    case Duplikat = 'duplikat';
    case PasskeyInvalid = 'passkey_invalid';
    case BelumMasuk = 'belum_masuk';
}
