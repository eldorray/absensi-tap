<?php

use App\Models\User;

test('percobaan001 mati secara bawaan dan terkirim ke halaman', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pengumuman.index'))
        ->assertInertia(fn ($page) => $page->where('percobaan.percobaan001', false));
});

test('percobaan001 bisa dinyalakan lewat config', function () {
    config(['absensi.percobaan.percobaan001' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('pengumuman.index'))
        ->assertInertia(fn ($page) => $page->where('percobaan.percobaan001', true));
});

test('letupan percobaan001 hanya dipicu saat absen berhasil dan flag menyala', function () {
    $tombol = file_get_contents(resource_path('js/components/TapButton.svelte'));

    expect($tombol)->toContain('onSuccess: () => rayakan(kotak)')
        ->and($tombol)->toContain('page.props.percobaan?.percobaan001');
});
