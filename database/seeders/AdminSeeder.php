<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Buat atau perbarui akun administrator utama.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrNew([
            'email' => 'fahmie@gmail.com',
        ]);

        $admin->forceFill([
            'name' => 'Fahmie',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => Role::Admin,
            'is_active' => true,
        ])->save();
    }
}
