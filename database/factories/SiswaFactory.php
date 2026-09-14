<?php

namespace Database\Factories;

use App\Enums\JenisKelamin;
use App\Models\Kantor;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kantor_id' => Kantor::factory(),
            'nis' => fake()->unique()->numerify('########'),
            'nisn' => fake()->unique()->numerify('##########'),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases()),
            'tanggal_lahir' => fake()->dateTimeBetween('-15 years', '-6 years'),
            'is_active' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
