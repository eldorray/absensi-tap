<?php

namespace Database\Factories;

use App\Models\Pengumuman;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengumuman>
 */
class PengumumanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'judul' => fake()->sentence(4),
            'isi' => fake()->sentence(12),
            'is_active' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
