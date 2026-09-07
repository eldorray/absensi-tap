<?php

use App\Models\Pengumuman;
use App\Models\User;

test('guru tidak boleh membuka menu pengumuman', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.pengumuman.index'))->assertForbidden();
});

test('admin melihat daftar pengumuman', function () {
    Pengumuman::factory()->create(['judul' => 'Rapat guru']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pengumuman.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/Pengumuman')
            ->has('pengumumans', 1)
            ->where('pengumumans.0.judul', 'Rapat guru'));
});

test('admin menambah pengumuman', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.pengumuman.store'), ['judul' => 'Rapat guru', 'isi' => 'Sabtu pukul 09.00 di aula.', 'is_active' => true])
        ->assertRedirect(route('admin.pengumuman.index'));

    expect(Pengumuman::where('judul', 'Rapat guru')->value('is_active'))->toBeTruthy();
});

test('judul pengumuman wajib diisi', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.pengumuman.store'), ['judul' => '', 'isi' => 'Isi', 'is_active' => true])
        ->assertSessionHasErrors('judul');

    expect(Pengumuman::count())->toBe(0);
});

test('admin menyembunyikan pengumuman', function () {
    $pengumuman = Pengumuman::factory()->create(['judul' => 'Rapat guru', 'isi' => 'Isi lama']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pengumuman.update', $pengumuman), ['judul' => 'Rapat guru', 'isi' => 'Isi baru', 'is_active' => false])
        ->assertRedirect(route('admin.pengumuman.index'));

    $pengumuman->refresh();

    expect($pengumuman->isi)->toBe('Isi baru')
        ->and($pengumuman->is_active)->toBeFalse();
});

test('admin menghapus pengumuman', function () {
    $pengumuman = Pengumuman::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.pengumuman.destroy', $pengumuman))
        ->assertRedirect(route('admin.pengumuman.index'));

    expect(Pengumuman::count())->toBe(0);
});

test('guru hanya melihat pengumuman yang aktif di halaman absen', function () {
    Pengumuman::factory()->create(['judul' => 'Tampil']);
    Pengumuman::factory()->nonaktif()->create(['judul' => 'Disembunyikan']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Dashboard')
            ->has('pengumumans', 1)
            ->where('pengumumans.0.judul', 'Tampil'));
});
