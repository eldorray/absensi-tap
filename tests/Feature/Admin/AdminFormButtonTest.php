<?php

test('setiap form admin punya satu tombol submit', function () {
    $halaman = [
        'admin/Guru.svelte',
        'admin/Pengaturan.svelte',
        'admin/JadwalGuru.svelte',
        'admin/Pengumuman.svelte',
        'admin/Kantor.svelte',
        'admin/TahunAjaran.svelte',
        'admin/User.svelte',
        'admin/Dashboard.svelte',
    ];

    foreach ($halaman as $berkas) {
        $isi = file_get_contents(resource_path('js/pages/'.$berkas));

        // Dicocokkan lewat atributnya saja: Prettier kerap memindahkan
        // type="submit" ke baris berikutnya, jadi '<Button type=' tidak andal.
        expect(substr_count($isi, 'type="submit"'))
            ->toBe(substr_count($isi, '<form'), $berkas);
    }
});
