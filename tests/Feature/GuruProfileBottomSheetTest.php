<?php

test('avatar guru membuka modal bottom sheet profil', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.svelte'));
    $profileSheet = file_get_contents(resource_path('js/components/GuruProfileSheet.svelte'));

    expect($layout)
        ->toContain('<GuruProfileSheet />')
        ->toContain('<ThemeToggle />')
        ->not->toContain('href={toUrl(profileEdit())}')
        ->and($profileSheet)
        ->toContain('<SheetContent')
        ->toContain('side="bottom"')
        ->toContain('aria-label="Buka profil"')
        ->toContain('avatar-kotak')
        ->toContain('foto-avatar')
        ->toContain('aspect-ratio: 1 / 1')
        ->toContain('object-fit: cover')
        ->toContain('Edit profil')
        ->toContain('Tampilan')
        ->toContain('Akun terverifikasi')
        ->toContain('Pengaturan akun')
        ->toContain('min-h-16')
        ->toContain('h-fit')
        ->toContain('inset-x-0')
        ->not->toContain('left-1/2')
        ->not->toContain('-translate-x-1/2')
        ->not->toContain('max-h-[92svh]')
        ->toContain('Keluar');

    // Keamanan akun disembunyikan dari guru: menunya hanya dirender untuk admin.
    expect($profileSheet)
        ->toContain('{#if isAdmin}')
        ->toContain('Keamanan akun');

    // Edit profil dan Tampilan dibuka sebagai panel di dalam sheet yang sama,
    // bukan pindah halaman.
    expect($profileSheet)
        ->toContain("bukaPanel('profil')")
        ->toContain("bukaPanel('tampilan')")
        ->toContain('<AppearanceTabs />')
        ->toContain('ProfileController.update()')
        ->not->toContain('profileEdit()')
        ->not->toContain('appearanceEdit()');

    expect(file_get_contents(resource_path('js/components/ui/sheet/SheetContent.svelte')))
        ->not->toContain("'fixed relative flex");
});
