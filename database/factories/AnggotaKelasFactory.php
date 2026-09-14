<?php

namespace Database\Factories;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnggotaKelas>
 */
class AnggotaKelasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'siswa_id' => Siswa::factory(),
            'tanggal_mulai' => now()->startOfYear(),
            'tanggal_selesai' => null,
            'is_active' => true,
        ];
    }
}
