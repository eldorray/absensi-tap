<?php

namespace App\Enums;

enum Role: string
{
    case Guru = 'guru';
    case Admin = 'admin';
    case OrangTua = 'orang_tua';

    public function label(): string
    {
        return match ($this) {
            self::Guru => 'Guru',
            self::Admin => 'Admin',
            self::OrangTua => 'Orang Tua',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Guru => 'Tap absen dari HP sendiri, ajukan izin, melihat jadwal dan riwayatnya, serta memantau kehadiran siswa kelas yang diampu (hanya baca).',
            self::Admin => 'Seluruh menu admin: rekap, guru, jadwal, kantor, user, tahun ajaran, pengaturan, dan mengisi absensi siswa seluruh sekolah sebagai guru piket.',
            self::OrangTua => 'Melihat kehadiran anak yang ditautkan admin. Tidak bisa tap absen dan tidak melihat data siswa lain.',
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
                'Memantau absensi siswa kelas yang diampu (hanya baca)',
                'Profil dan tampilan',
            ],
            self::Admin => [
                'Mengisi absensi siswa seluruh sekolah (guru piket)',
                'Rekap harian dan bulanan',
                'Kelola guru dan perangkat',
                'Jadwal guru',
                'Kelola kantor',
                'Kelola user dan role',
                'Tahun ajaran',
                'Pengumuman',
                'Pengaturan lokasi dan jam absen',
            ],
            self::OrangTua => [
                'Kehadiran anak yang ditautkan',
                'Riwayat dan ringkasan kehadiran anak',
                'Profil dan tampilan',
            ],
        };
    }
}
