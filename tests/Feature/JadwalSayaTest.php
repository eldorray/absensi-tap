<?php

use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

test('guru dapat melihat jadwal kerja mingguan dengan penanda hari ini', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');
    $this->seed(JadwalKerjaSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('jadwal.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jadwal/Index')
            ->has('jadwals', 7)
            ->where('hariIni', 1)
            ->where('jadwals.1.nama_hari', 'Senin')
            ->where('jadwals.1.jam_masuk', '07:00')
            ->where('jadwals.1.jam_pulang', '14:00')
            ->where('jadwals.1.is_hari_kerja', true)
        );
});

test('tamu tidak dapat membuka jadwal saya', function () {
    $this->get(route('jadwal.index'))->assertRedirect(route('login'));
});

test('avatar header menuju profil dan navbar bawah memakai jadwal saya', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.svelte'));
    $navigation = file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte'));

    expect($layout)
        ->toContain('href={toUrl(profileEdit())}')
        ->toContain('aria-label="Buka profil"')
        ->and($navigation)
        ->toContain("label: 'Jadwal Saya'")
        ->toContain('@/routes/jadwal')
        ->not->toContain("label: 'Profil'");
});
