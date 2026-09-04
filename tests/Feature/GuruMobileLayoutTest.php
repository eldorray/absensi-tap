<?php

test('layout guru memakai navigasi bawah mobile sementara admin tetap memakai sidebar', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.svelte'));
    $mobileNavigation = file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte'));

    expect($layout)
        ->toContain('{#if isAdmin}')
        ->toContain('<AppSidebar />')
        ->toContain('<GuruBottomNavigation />')
        ->and($mobileNavigation)
        ->toContain('Absensi')
        ->toContain('Izin')
        ->toContain('Profil')
        ->toContain('Riwayat')
        ->toContain('#riwayat-absensi')
        ->not->toContain("label: 'Keamanan'")
        ->toContain('env(safe-area-inset-bottom)');

    expect(file_get_contents(resource_path('js/pages/Dashboard.svelte')))
        ->toContain('id="riwayat-absensi"');
});
