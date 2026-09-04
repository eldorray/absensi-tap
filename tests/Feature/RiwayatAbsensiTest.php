<?php

use App\Models\Absensi;
use App\Models\User;
use Illuminate\Support\Carbon;

test('menu absensi tidak lagi mengirim riwayat', function () {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->missing('riwayat')
        );
});

test('halaman riwayat hanya menampilkan 30 hari terakhir milik guru sendiri', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');

    [$guru] = guruSiapAbsen();
    Absensi::factory()->for($guru)->create(['tanggal' => today()->subDays(5)]);
    Absensi::factory()->for($guru)->create(['tanggal' => today()->subDays(40)]);
    Absensi::factory()->for(User::factory())->create(['tanggal' => today()]);

    $this->actingAs($guru)
        ->get(route('riwayat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('riwayat/Index')
            ->has('riwayat', 1)
            ->where('riwayat.0.tanggal', today()->subDays(5)->toDateString())
        );
});

test('tamu tidak dapat membuka riwayat absensi', function () {
    $this->get(route('riwayat.index'))->assertRedirect(route('login'));
});
