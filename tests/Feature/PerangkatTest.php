<?php

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

test('satu HP tidak bisa terdaftar di dua akun', function () {
    $uuid = (string) Str::uuid();

    Perangkat::factory()->for(User::factory())->create(['uuid' => $uuid]);

    expect(fn () => Perangkat::factory()->for(User::factory())->create(['uuid' => $uuid]))
        ->toThrow(QueryException::class);
});

test('perangkat default berstatus active', function () {
    expect(Perangkat::factory()->for(User::factory())->create()->status)
        ->toBe(StatusPerangkat::Active);
});

test('state pending dan revoked tersedia', function () {
    expect(Perangkat::factory()->for(User::factory())->pending()->create()->status)
        ->toBe(StatusPerangkat::Pending)
        ->and(Perangkat::factory()->for(User::factory())->revoked()->create()->status)
        ->toBe(StatusPerangkat::Revoked);
});

test('perangkat terhubung ke pemiliknya', function () {
    $guru = User::factory()->create();
    $perangkat = Perangkat::factory()->for($guru)->create();

    expect($perangkat->user->id)->toBe($guru->id);
});
