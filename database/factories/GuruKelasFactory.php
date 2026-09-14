<?php

namespace Database\Factories;

use App\Models\GuruKelas;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuruKelas>
 */
class GuruKelasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'user_id' => User::factory(),
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
        ];
    }
}
