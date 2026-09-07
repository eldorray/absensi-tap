<?php

namespace Database\Factories;

use App\Models\Kantor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kantor>
 */
class KantorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'MI '.fake()->unique()->lastName(),
            'jenjang' => fake()->randomElement(['MI', 'SMP']),
            'alamat' => fake()->streetAddress(),
            'is_active' => true,
        ];
    }
}
