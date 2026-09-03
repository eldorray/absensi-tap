<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('aplikasi memakai waktu Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Jakarta');
});

test('guru baru default role guru dan aktif', function () {
    $guru = User::factory()->create();

    expect($guru->role)->toBe(Role::Guru)
        ->and($guru->is_active)->toBeTrue()
        ->and($guru->nip)->toBeNull();
});

test('guru tidak lolos gate admin', function () {
    expect(Gate::forUser(User::factory()->create())->allows('admin'))->toBeFalse();
});

test('admin lolos gate admin', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('admin'))->toBeTrue();
});

test('admin nonaktif tidak lolos gate admin', function () {
    $admin = User::factory()->admin()->create(['is_active' => false]);

    expect(Gate::forUser($admin)->allows('admin'))->toBeFalse();
});

test('role dan is_active tidak bisa diisi mass assignment', function () {
    $guru = new User;
    $guru->fill(['name' => 'Bu Aminah', 'email' => 'aminah@example.test', 'role' => 'admin', 'is_active' => false]);

    expect($guru->role)->toBeNull()
        ->and($guru->is_active)->toBeNull();
});
