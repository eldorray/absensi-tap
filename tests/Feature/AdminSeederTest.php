<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;

test('admin seeder membuat akun admin Fahmie yang aktif dan terverifikasi', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::query()->where('email', 'fahmie@gmail.com')->firstOrFail();

    expect($admin->name)->toBe('Fahmie')
        ->and($admin->role)->toBe(Role::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});

test('admin seeder dapat dijalankan ulang tanpa menduplikasi akun', function () {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    expect(User::query()->where('email', 'fahmie@gmail.com')->count())->toBe(1);
});
