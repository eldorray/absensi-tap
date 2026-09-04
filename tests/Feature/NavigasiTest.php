<?php

use App\Models\User;

test('guru mendapat flag isAdmin false', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isAdmin', false));
});

test('admin mendapat flag isAdmin true', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isAdmin', true));
});

test('semua route admin tertutup untuk guru', function (string $nama, array $parameter) {
    $this->actingAs(User::factory()->create())
        ->get(route($nama, $parameter))
        ->assertForbidden();
})->with([
    ['admin.rekap.index', []],
    ['admin.izin.index', []],
    ['admin.guru.index', []],
    ['admin.pengaturan.edit', []],
]);

test('perangkat terikat muncul di halaman keamanan', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('perangkats', 1));
});
