<?php

namespace Database\Seeders;

use App\Models\JadwalKerja;
use Illuminate\Database\Seeder;

class JadwalKerjaSeeder extends Seeder
{
    /**
     * Senin sampai Sabtu 07:00-14:00 dengan toleransi 10 menit; Minggu bukan hari kerja.
     *
     * Dibuat idempotent supaya aman dijalankan ulang di server yang sudah berjalan.
     */
    public function run(): void
    {
        foreach (range(0, 6) as $day) {
            JadwalKerja::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'jam_masuk' => '07:00:00',
                    'jam_pulang' => '14:00:00',
                    'toleransi_menit' => 10,
                    'is_hari_kerja' => $day !== 0,
                ],
            );
        }
    }
}
