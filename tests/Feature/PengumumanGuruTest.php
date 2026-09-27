<?php

use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guru melihat pengumuman aktif, terbaru lebih dulu', function () {
    Pengumuman::factory()->create(['judul' => 'Rapat guru', 'is_active' => true, 'created_at' => Carbon::parse('2026-09-01 07:00')]);
    Pengumuman::factory()->create(['judul' => 'Libur maulid', 'is_active' => true, 'created_at' => Carbon::parse('2026-09-10 07:00')]);
    Pengumuman::factory()->create(['judul' => 'Draf', 'is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('pengumuman.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('pengumuman/Index')
            ->has('pengumumans.data', 2)
            ->where('pengumumans.data.0.judul', 'Libur maulid')
            ->where('pengumumans.data.1.judul', 'Rapat guru')
            ->has('pengumumans.data.0.dibuat'));
});

test('pengumuman tahun ajaran lain tidak tampil', function () {
    Pengumuman::factory()->create(['judul' => 'Tahun lalu', 'is_active' => true]);
    tahunAjaranAktif('2027/2028');

    $this->actingAs(User::factory()->create())
        ->get(route('pengumuman.index'))
        ->assertInertia(fn ($page) => $page->has('pengumumans.data', 0));
});

test('pengumuman dipaginasi sepuluh per halaman', function () {
    Pengumuman::factory()->count(12)->create(['is_active' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('pengumuman.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('pengumumans.data', 2)->where('pengumumans.last_page', 2));
});

test('orang tua dan tamu tidak bisa membuka pengumuman guru', function () {
    $this->get(route('pengumuman.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->orangTua()->create())
        ->get(route('pengumuman.index'))
        ->assertForbidden();
});

test('detail pengumuman muncul dari bawah dan bisa digeser ke pengumuman lain', function () {
    $detail = file_get_contents(resource_path('js/components/PengumumanDetail.svelte'));

    expect($detail)->toContain('<Sheet bind:open>')
        ->and($detail)->toContain('side="bottom"')
        ->and($detail)->toContain('snap-x snap-mandatory')
        ->and($detail)->toContain('aria-label="Pengumuman berikutnya"');

    // Halaman dan dashboard memakai kartu dan detail yang sama.
    foreach (['js/pages/pengumuman/Index.svelte', 'js/pages/Dashboard.svelte'] as $halaman) {
        expect(file_get_contents(resource_path($halaman)))
            ->toContain('<PengumumanKartu')
            ->toContain('<PengumumanDetail');
    }
});

test('dashboard mengirim lima pengumuman terbaru beserta waktunya', function () {
    Pengumuman::factory()->count(6)->create(['is_active' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('pengumumans', 5)->has('pengumumans.0.dibuat'));
});
