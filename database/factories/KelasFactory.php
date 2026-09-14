<?php

namespace Database\Factories;

use App\Models\Kantor;
use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tingkat = fake()->numberBetween(1, 6);

        return [
            'kantor_id' => Kantor::factory(),
            'nama' => $tingkat.fake()->randomElement(['A', 'B', 'C']),
            'tingkat' => $tingkat,
            'wali_kelas_id' => null,
            'is_active' => true,
        ];
    }
}
