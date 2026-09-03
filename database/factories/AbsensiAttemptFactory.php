<?php

namespace Database\Factories;

use App\Enums\HasilTap;
use App\Enums\TipeTap;
use App\Models\AbsensiAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AbsensiAttempt>
 */
class AbsensiAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipe' => TipeTap::Masuk,
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'accuracy_meter' => 15,
            'jarak_meter' => 20,
            'perangkat_uuid' => (string) Str::uuid(),
            'terverifikasi' => false,
            'hasil' => HasilTap::Diterima,
        ];
    }
}
