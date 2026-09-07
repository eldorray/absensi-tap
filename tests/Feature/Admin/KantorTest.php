<?php

use App\Models\Kantor;
use App\Models\Lokasi;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 07:00:00');
});

test('guru tidak boleh membuka kelola kantor', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kantor.index'))->assertForbidden();
});

test('admin menambah, mengubah, dan menghapus kantor', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.kantor.store'), [
        'nama' => 'SMP Syekh Yusuf',
        'jenjang' => 'SMP',
        'alamat' => 'Jl. Merdeka 1',
        'is_active' => true,
    ])->assertRedirect(route('admin.kantor.index'));

    $kantor = Kantor::where('nama', 'SMP Syekh Yusuf')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.kantor.update', $kantor), [
        'nama' => 'SMP Syekh Yusuf',
        'jenjang' => 'SMP',
        'alamat' => 'Jl. Merdeka 2',
        'is_active' => false,
    ])->assertRedirect();

    expect($kantor->refresh()->alamat)->toBe('Jl. Merdeka 2')
        ->and($kantor->is_active)->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.kantor.destroy', $kantor))->assertRedirect();

    expect(Kantor::count())->toBe(0);
});

test('menghapus kantor melepas penugasan tanpa menghapus guru dan lokasinya', function () {
    $kantor = Kantor::factory()->create();
    $guru = User::factory()->create(['kantor_id' => $kantor->id]);
    $lokasi = Lokasi::factory()->create(['kantor_id' => $kantor->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.kantor.destroy', $kantor));

    expect($guru->refresh()->kantor_id)->toBeNull()
        ->and($lokasi->refresh()->kantor_id)->toBeNull()
        ->and(User::whereKey($guru->id)->exists())->toBeTrue()
        ->and(Lokasi::whereKey($lokasi->id)->exists())->toBeTrue();
});

test('guru yang punya kantor hanya diukur ke lokasi kantornya dan lokasi bersama', function () {
    $this->seed(JadwalKerjaSeeder::class);

    $kantorSendiri = Kantor::factory()->create(['nama' => 'MI']);
    $kantorLain = Kantor::factory()->create(['nama' => 'SMP']);

    // Lokasi kantor lain ditaruh tepat di titik guru, tapi tidak boleh dipakai.
    Lokasi::factory()->create([
        'kantor_id' => $kantorLain->id,
        'nama' => 'Gerbang SMP',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
    ]);

    $guru = User::factory()->create(['kantor_id' => $kantorSendiri->id]);

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($p) => $p->has('lokasis', 0));

    Lokasi::factory()->create([
        'kantor_id' => $kantorSendiri->id,
        'nama' => 'Gerbang MI',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
    ]);

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($p) => $p->has('lokasis', 1)
            ->where('lokasis.0.nama', 'Gerbang MI'));
});

test('tap guru ditolak kalau kantornya belum punya lokasi', function () {
    $this->seed(JadwalKerjaSeeder::class);

    [$guru, $perangkat, $lokasi] = guruSiapAbsen();
    $guru->forceFill(['kantor_id' => Kantor::factory()->create()->id])->save();
    // Satu-satunya lokasi dimiliki kantor lain, jadi tidak berlaku untuk guru ini.
    $lokasi->update(['kantor_id' => Kantor::factory()->create()->id]);

    $this->actingAs($guru)
        ->post(route('absensi.store'), [
            'tipe' => 'masuk',
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'accuracy' => 12,
            'device_uuid' => $perangkat->uuid,
        ])
        ->assertSessionHasErrors('tap');
});

test('lokasi tanpa kantor tetap dipakai guru yang sudah ditugaskan', function () {
    $this->seed(JadwalKerjaSeeder::class);

    [$guru, $perangkat] = guruSiapAbsen();
    $guru->forceFill(['kantor_id' => Kantor::factory()->create()->id])->save();

    $this->actingAs($guru)
        ->post(route('absensi.store'), [
            'tipe' => 'masuk',
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'accuracy' => 12,
            'device_uuid' => $perangkat->uuid,
        ])
        ->assertSessionHasNoErrors();
});

test('kantor tidak ikut terhapus saat tahun ajaran berganti', function () {
    $kantor = Kantor::factory()->create();
    $baru = TahunAjaran::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tahun-ajaran.aktifkan', $baru));

    expect(Kantor::whereKey($kantor->id)->exists())->toBeTrue();
});
