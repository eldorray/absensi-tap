<?php

use App\Enums\Role;
use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guru dilarang', fn () => $this->actingAs(User::factory()->create())->get(route('admin.guru.index'))->assertForbidden());
test('admin melihat guru', function () {
    $g = User::factory()->create();
    Perangkat::factory()->for($g)->create();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.guru.index'))->assertOk()->assertInertia(fn ($p) => $p->component('admin/Guru')->has('gurus', 1));
});
test('admin membuat guru', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.guru.store'), ['name' => 'A', 'nip' => '1', 'email' => 'a@test.test', 'password' => 'rahasia-panjang-sekali'])->assertRedirect();
    $g = User::whereEmail('a@test.test')->firstOrFail();
    expect($g->role)->toBe(Role::Guru)->and(Hash::check('rahasia-panjang-sekali', $g->password))->toBeTrue()->and($g->email_verified_at)->not->toBeNull();
});
test('email unik', function () {
    $g = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.guru.store'), ['name' => 'X', 'email' => $g->email, 'password' => 'rahasia-panjang-sekali'])->assertSessionHasErrors('email');
});
test('nonaktifkan guru', function () {
    $g = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create())->patch(route('admin.guru.update', $g), ['is_active' => false]);
    expect($g->refresh()->is_active)->toBeFalse();
});
test('approve mencabut lama', function () {
    $a = User::factory()->admin()->create();
    $g = User::factory()->create();
    $lama = Perangkat::factory()->for($g)->create();
    $baru = Perangkat::factory()->for($g)->pending()->create();
    $this->actingAs($a)->patch(route('admin.perangkat.update', $baru), ['status' => 'active']);
    expect($baru->refresh()->status)->toBe(StatusPerangkat::Active)->and($lama->refresh()->status)->toBe(StatusPerangkat::Revoked)->and($baru->approved_by)->toBe($a->id);
});
test('cabut perangkat', function () {
    $p = Perangkat::factory()->for(User::factory())->create();
    $this->actingAs(User::factory()->admin()->create())->patch(route('admin.perangkat.update', $p), ['status' => 'revoked']);
    expect($p->refresh()->status)->toBe(StatusPerangkat::Revoked);
});
test('guru tidak approve', function () {
    $g = User::factory()->create();
    $p = Perangkat::factory()->for($g)->pending()->create();
    $this->actingAs($g)->patch(route('admin.perangkat.update', $p), ['status' => 'active'])->assertForbidden();
});
