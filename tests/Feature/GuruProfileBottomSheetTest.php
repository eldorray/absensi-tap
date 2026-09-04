<?php

test('avatar guru membuka modal bottom sheet profil', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.svelte'));
    $profileSheet = file_get_contents(resource_path('js/components/GuruProfileSheet.svelte'));

    expect($layout)
        ->toContain('<GuruProfileSheet />')
        ->not->toContain('href={toUrl(profileEdit())}')
        ->and($profileSheet)
        ->toContain('<SheetContent')
        ->toContain('side="bottom"')
        ->toContain('aria-label="Buka profil"')
        ->toContain('Edit profil')
        ->toContain('Keamanan akun')
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

    expect(file_get_contents(resource_path('js/components/ui/sheet/SheetContent.svelte')))
        ->not->toContain("'fixed relative flex");
});
