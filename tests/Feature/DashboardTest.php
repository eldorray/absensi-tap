<?php

use Illuminate\Support\Carbon;

test('tamu diarahkan ke halaman login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('guru melihat halaman tap dengan jadwal hari ini', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');

    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('jadwal.jam_masuk', '07:00')
            ->where('jadwal.jam_pulang', '14:00')
            ->where('jadwal.is_hari_kerja', true)
            ->where('hariIni', null)
            ->where('namaLokasi', 'Gerbang Utama')
            ->where('punyaPasskey', false)
        );
});

test('status hari ini muncul setelah tap masuk', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');

    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ]);

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('hariIni.status', 'hadir')
            ->where('hariIni.jam_masuk', '07:00')
            ->where('hariIni.jam_pulang', null)
        );
});

test('dashboard absensi tidak memuat data riwayat', function () {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->missing('riwayat'));
});

test('uuid perangkat dari cookie diteruskan sebagai prop', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->withCookie('perangkat_uuid', $perangkat->uuid)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('perangkatUuidTersimpan', $perangkat->uuid));
});
