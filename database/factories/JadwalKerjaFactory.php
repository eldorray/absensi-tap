<?php

namespace Database\Factories;

use App\Models\JadwalKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JadwalKerja>
 */
class JadwalKerjaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->unique()->numberBetween(0, 6),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '14:00:00',
            'toleransi_menit' => 10,
            'is_hari_kerja' => true,
        ];
    }
}
