<?php

namespace App\Enums;

/**
 * Jenis kelamin siswa seperti tertulis di buku induk.
 *
 * Disimpan sebagai satu huruf karena itu yang dipakai berkas Dapodik dan
 * ekspor Excel sekolah; labelnya dipanjangkan hanya saat dibaca manusia.
 */
enum JenisKelamin: string
{
    case LakiLaki = 'L';
    case Perempuan = 'P';

    public function label(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }
}
