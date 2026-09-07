<?php

namespace App\Enums;

enum Role: string
{
    case Guru = 'guru';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Guru => 'Guru',
            self::Admin => 'Admin',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Guru => 'Tap absen dari HP sendiri, ajukan izin, dan melihat jadwal serta riwayatnya.',
            self::Admin => 'Seluruh menu admin: rekap, guru, jadwal, kantor, user, tahun ajaran, dan pengaturan.',
        };
    }

    /**
     * Menu yang dijangkau role ini. Dipakai halaman kelola role supaya izinnya
     * terbaca manusia, bukan cuma tersembunyi di gate.
     *
     * @return list<string>
     */
    public function akses(): array
    {
        return match ($this) {
            self::Guru => [
                'Absensi (tap masuk dan pulang)',
                'Izin dan riwayat sendiri',
                'Jadwal sendiri',
                'Profil dan tampilan',
            ],
            self::Admin => [
                'Rekap harian dan bulanan',
                'Kelola guru dan perangkat',
                'Jadwal guru',
                'Kelola kantor',
                'Kelola user dan role',
                'Tahun ajaran',
                'Pengumuman',
                'Pengaturan lokasi dan jam absen',
            ],
        };
    }
}
