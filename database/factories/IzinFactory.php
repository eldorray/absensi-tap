<?php

namespace Database\Factories;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\Izin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Izin>
 */
class IzinFactory extends Factory
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
            'tipe' => TipeIzin::Izin,
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
            'alasan' => 'Mengurus administrasi keluarga.',
            'status' => StatusIzin::Pending,
        ];
    }

    public function disetujui(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusIzin::Disetujui,
            'reviewed_at' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusIzin::Ditolak,
            'reviewed_at' => now(),
        ]);
    }
}
