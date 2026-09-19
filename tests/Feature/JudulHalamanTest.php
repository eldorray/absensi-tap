<?php

test('judul halaman memakai nama aplikasi dari pengaturan', function () {
    $head = file_get_contents(resource_path('js/components/AppHead.svelte'));
    expect($head)->toContain('page.props.name')->not->toContain('VITE_APP_NAME');

    $bootstrap = file_get_contents(resource_path('js/app.ts'));

    expect($bootstrap)
        ->toContain('page.props.name')
        ->not->toContain("VITE_APP_NAME || 'Laravel'");
});
