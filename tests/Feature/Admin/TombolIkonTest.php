<?php

test('aksi per baris di seluruh halaman admin memakai tombol ikon', function () {
    $halaman = [
        'admin/User.svelte',
        'admin/Guru.svelte',
        'admin/Kantor.svelte',
        'admin/Pengumuman.svelte',
        'admin/Pengaturan.svelte',
        'admin/TahunAjaran.svelte',
        'admin/JadwalGuru.svelte',
        'admin/RekapHarian.svelte',
        'admin/Izin.svelte',
    ];

    foreach ($halaman as $berkas) {
        $isi = file_get_contents(resource_path('js/pages/'.$berkas));

        expect($isi)->toContain('<TombolIkon');

        // Tidak ada lagi tombol teks untuk aksi baris; yang tersisa hanya
        // tombol form dan aksi tingkat halaman.
        expect($isi)
            ->not->toContain('>Hapus</Button')
            ->not->toContain('Hapus\n                        </Button')
            ->not->toContain('>Setujui</Button')
            ->not->toContain('>Cabut</Button');
    }
});

test('tombol ikon punya nama terbaca, kursor tangan, dan keadaan nonaktif', function () {
    $komponen = file_get_contents(resource_path('js/components/TombolIkon.svelte'));

    expect($komponen)
        ->toContain('title={label}')
        ->toContain('aria-label={label}')
        ->toContain('{disabled}')
        ->toContain('disabled:pointer-events-none')
        // Warna hanya muncul saat hover supaya deret ikon tidak ramai.
        ->toContain('hover:bg-destructive/10 hover:text-destructive')
        ->toContain('hover:bg-emerald-500/10');
});
