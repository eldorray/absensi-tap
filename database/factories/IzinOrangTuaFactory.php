<?php

namespace Database\Factories;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\IzinOrangTua;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IzinOrangTua> */
class IzinOrangTuaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->orangTua(),
            'siswa_id' => Siswa::factory(),
            'nama_pengaju' => fake()->name(),
            'email_pengaju' => fake()->safeEmail(),
            'tipe' => TipeIzin::Izin,
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
            'alasan' => 'Ada keperluan keluarga.',
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
