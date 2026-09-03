<?php

namespace Database\Factories;

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Perangkat>
 */
class PerangkatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'label' => 'Android',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14)',
            'status' => StatusPerangkat::Active,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPerangkat::Pending,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPerangkat::Revoked,
        ]);
    }
}
