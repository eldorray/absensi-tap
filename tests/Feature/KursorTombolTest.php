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
