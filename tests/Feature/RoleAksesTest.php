<?php

use App\Enums\Role;
use App\Models\User;

test('enum role mengenal tiga role', function () {
    expect(array_map(fn (Role $r): string => $r->value, Role::cases()))
        ->toBe(['guru', 'admin', 'orang_tua']);
});

test('halaman kelola role menampilkan orang tua beserta aksesnya', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.role.index'))
        ->assertInertia(fn ($p) => $p->has('roles', 3)
            ->where('roles.2.value', 'orang_tua')
            ->where('roles.2.label', 'Orang Tua')
            ->where('roles.2.jumlah', 0));
});

test('orang tua ditolak dari seluruh halaman guru', function () {
    $orangTua = User::factory()->create(['role' => Role::OrangTua]);

    foreach (['dashboard', 'jadwal.index', 'riwayat.index', 'izin.index'] as $rute) {
        $this->actingAs($orangTua)->get(route($rute))->assertForbidden();
    }
});

test('orang tua ditolak dari endpoint mutasi guru', function () {
    $orangTua = User::factory()->create(['role' => Role::OrangTua]);

    $this->actingAs($orangTua)->post(route('absensi.store'), [])->assertForbidden();
    $this->actingAs($orangTua)->post(route('perangkat.store'), [])->assertForbidden();
});

test('orang tua ditolak dari halaman admin', function () {
    $this->actingAs(User::factory()->create(['role' => Role::OrangTua]))
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('guru dan admin tetap bisa membuka halaman absensi', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertOk();
});

test('role ikut dikirim sebagai shared prop', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p->where('auth.role', 'admin')
            ->where('auth.isAdmin', true));
});
