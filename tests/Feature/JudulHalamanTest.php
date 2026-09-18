<?php

test('judul halaman memakai nama aplikasi dari pengaturan', function () {
    $bootstrap = file_get_contents(resource_path('js/app.ts'));

    expect($bootstrap)
        ->toContain('page.props.name')
        ->not->toContain("VITE_APP_NAME || 'Laravel'");
});
