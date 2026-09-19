<?php

use App\Models\Siswa;
use App\Models\User;

beforeEach(fn () => tahunAjaranAktif('2026/2027'));

test('checkbox orang tua memperbarui payload form yang dikirim', function () {
    $halaman = file_get_contents(resource_path('js/pages/admin/Siswa.svelte'));

    expect($halaman)
        ->toContain('onclick={() =>')
        ->toContain('ubahPilihanOrangTua(')
        ->not->toContain('onCheckedChange=');
});

test('admin dapat menautkan beberapa orang tua dan mengganti tautannya secara atomik', function () {
    $admin = User::factory()->admin()->create();
    $siswa = Siswa::factory()->create();
    $ayah = User::factory()->orangTua()->create();
    $ibu = User::factory()->orangTua()->create();

    $this->actingAs($admin)->put(route('admin.siswa.orang-tua.update', $siswa), [
        'user_ids' => [$ayah->id, $ibu->id],
    ])->assertRedirect(route('admin.siswa.index'));

    expect($siswa->orangTuas()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$ayah->id, $ibu->id]);

    $this->actingAs($admin)->put(route('admin.siswa.orang-tua.update', $siswa), [
        'user_ids' => [$ibu->id],
    ])->assertRedirect();

    expect($siswa->orangTuas()->pluck('users.id')->all())->toBe([$ibu->id]);
});

test('admin dapat mencabut semua tautan tanpa menghapus siswa atau akun', function () {
    $admin = User::factory()->admin()->create();
    $siswa = Siswa::factory()->create();
    $orangTua = User::factory()->orangTua()->create();
    $siswa->orangTuas()->attach($orangTua);

    $this->actingAs($admin)->put(route('admin.siswa.orang-tua.update', $siswa), [
        'user_ids' => [],
    ])->assertRedirect();

    expect($siswa->orangTuas()->count())->toBe(0)
        ->and(Siswa::query()->whereKey($siswa->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($orangTua->id)->exists())->toBeTrue();
});

test('akun guru nonaktif dan id duplikat ditolak sebagai validasi', function () {
    $admin = User::factory()->admin()->create();
    $siswa = Siswa::factory()->create();
    $guru = User::factory()->create();
    $nonaktif = User::factory()->orangTua()->create(['is_active' => false]);
    $orangTua = User::factory()->orangTua()->create();

    $this->actingAs($admin)->from(route('admin.siswa.index'))
        ->put(route('admin.siswa.orang-tua.update', $siswa), [
            'user_ids' => [$guru->id, $nonaktif->id, $orangTua->id, $orangTua->id],
        ])->assertRedirect(route('admin.siswa.index'))
        ->assertSessionHasErrors(['user_ids.0', 'user_ids.1', 'user_ids.3']);

    expect($siswa->orangTuas()->count())->toBe(0);
});

test('guru dan orang tua tidak dapat mengubah tautan siswa', function () {
    $siswa = Siswa::factory()->create();
    $orangTua = User::factory()->orangTua()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('admin.siswa.orang-tua.update', $siswa), ['user_ids' => [$orangTua->id]])
        ->assertForbidden();

    $this->actingAs($orangTua)
        ->put(route('admin.siswa.orang-tua.update', $siswa), ['user_ids' => []])
        ->assertForbidden();
});

test('halaman siswa menyediakan akun orang tua aktif dan tautan saat ini', function () {
    $admin = User::factory()->admin()->create();
    $siswa = Siswa::factory()->create(['nama' => 'Aisyah']);
    $orangTua = User::factory()->orangTua()->create(['name' => 'Wali Aisyah']);
    User::factory()->orangTua()->create(['name' => 'Wali Nonaktif', 'is_active' => false]);
    $siswa->orangTuas()->attach($orangTua);

    $this->actingAs($admin)->get(route('admin.siswa.index'))
        ->assertInertia(fn ($page) => $page
            ->has('orangTuas', 1)
            ->where('orangTuas.0.id', $orangTua->id)
            ->where('siswas.data.0.orang_tuas.0.id', $orangTua->id));
});
