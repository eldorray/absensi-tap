<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('versi aplikasi dibagikan ke seluruh halaman inertia', function () {
    config()->set('app.version', '1.2.3');

    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('versi', '1.2.3'));
});

test('versi dan kredit F.A.K tampil di sidebar admin, sheet profil guru, dan halaman auth', function () {
    $versi = file_get_contents(resource_path('js/components/AppVersion.svelte'));

    expect($versi)
        ->toContain('page.props.versi')
        ->toContain('Crafted by F.A.K')
        ->toContain('https://fahmiealkhudhorie.site/')
        ->toContain('rel="noopener noreferrer"');

    expect(file_get_contents(resource_path('js/components/AppSidebar.svelte')))
        ->toContain('<AppVersion />');

    expect(file_get_contents(resource_path('js/components/GuruProfileSheet.svelte')))
        ->toContain('<AppVersion class="mt-1" />');

    expect(file_get_contents(resource_path('js/layouts/auth/AuthSimpleLayout.svelte')))
        ->toContain('<AppVersion class="mt-10" />');
});
