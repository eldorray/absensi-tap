<?php

use App\Actions\Absensi\DaftarkanPerangkat;
use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Str;

test('HP pertama langsung aktif dan cookie dipasang', function () {
    $guru = User::factory()->create();
    $uuid = (string) Str::uuid();

    $response = $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);

    $response->assertRedirect(route('dashboard'))
        ->assertCookie('perangkat_uuid', $uuid);

    expect(Perangkat::where('uuid', $uuid)->value('status'))->toBe(StatusPerangkat::Active);
});

test('HP kedua masuk pending menunggu approve admin', function () {
    $guru = User::factory()->create();
    Perangkat::factory()->for($guru)->create();

    $uuid = (string) Str::uuid();
    $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);

    expect(Perangkat::where('uuid', $uuid)->value('status'))->toBe(StatusPerangkat::Pending)
        ->and(Perangkat::where('user_id', $guru->id)->count())->toBe(2);
});

test('HP milik guru lain ditolak', function () {
    $lain = User::factory()->create();
    $perangkat = Perangkat::factory()->for($lain)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('perangkat.store'), ['device_uuid' => $perangkat->uuid])
        ->assertSessionHasErrors('device_uuid');

    expect(Perangkat::where('uuid', $perangkat->uuid)->count())->toBe(1);
});

test('mendaftarkan uuid yang sama dua kali tidak menambah baris', function () {
    $guru = User::factory()->create();
    $uuid = (string) Str::uuid();

    $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);
    $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);

    expect(Perangkat::where('user_id', $guru->id)->count())->toBe(1);
});

test('uuid wajib berformat uuid', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('perangkat.store'), ['device_uuid' => 'bukan-uuid'])
        ->assertSessionHasErrors('device_uuid');
});

test('tamu tidak bisa mendaftarkan perangkat', function () {
    $this->post(route('perangkat.store'), ['device_uuid' => (string) Str::uuid()])
        ->assertRedirect(route('login'));
});

test('label diturunkan dari user agent', function () {
    expect(DaftarkanPerangkat::label('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'))->toBe('iPhone')
        ->and(DaftarkanPerangkat::label('Mozilla/5.0 (Linux; Android 14)'))->toBe('Android')
        ->and(DaftarkanPerangkat::label(null))->toBe('Perangkat lain');
});
