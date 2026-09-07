<?php

test('tombol memakai kursor tangan', function () {
    // Tailwind v4 menghapus cursor: pointer bawaan untuk <button>. Tanpa aturan
    // ini setiap tombol di aplikasi tampil dengan panah, termasuk tombol ikon.
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('button:not(:disabled)')
        ->toContain('cursor: pointer;')
        ->toContain('button:disabled')
        ->toContain('cursor: not-allowed;');
});

test('halaman admin menerima gesture scroll native', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.svelte'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($layout)
        ->toContain('class="admin-scroll min-w-0 overflow-x-clip"')
        ->and($css)
        ->toContain('.admin-scroll')
        ->toContain('touch-action: auto')
        ->toContain('overflow-y: auto')
        ->not->toContain("body {\n        width: 100%;\n        min-height: 100%;\n        overflow-x: hidden;\n        overscroll-behavior: none;\n        touch-action: pan-y;");
});
