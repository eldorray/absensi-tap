<?php

test('semua tombol pengirim form admin bertipe submit', function () {
    $guru = file_get_contents(resource_path('js/pages/admin/Guru.svelte'));
    $pengaturan = file_get_contents(resource_path('js/pages/admin/Pengaturan.svelte'));

    expect($guru)->toContain('<Button type="submit"')
        ->and(substr_count($pengaturan, '<Button type="submit"'))->toBe(3);
});
