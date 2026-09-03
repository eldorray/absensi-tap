<?php

namespace Database\Factories;

use App\Models\Lokasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lokasi>
 */
class LokasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'Gerbang Utama',
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'radius_meter' => 100,
            'is_active' => true,
        ];
    }
}
