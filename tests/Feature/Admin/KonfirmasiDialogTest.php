<?php

test('setiap tindakan merusak memakai modal konfirmasi, bukan confirm bawaan peramban', function () {
    $halaman = [
        'admin/Pengaturan.svelte',
        'admin/Guru.svelte',
        'admin/User.svelte',
        'admin/Kantor.svelte',
        'admin/Pengumuman.svelte',
        'admin/JadwalGuru.svelte',
        'admin/RekapHarian.svelte',
        'admin/TahunAjaran.svelte',
    ];

    foreach ($halaman as $berkas) {
        $isi = file_get_contents(resource_path('js/pages/'.$berkas));

        // toContain menerima banyak needle, jadi nama berkasnya tidak boleh
        // dikirim sebagai argumen kedua -- itu ikut dicari sebagai teks.
        expect($isi)
            // window.confirm memblokir, tidak bisa ditata, dan di beberapa
            // peramban HP bisa dibungkam pengguna sampai tak pernah muncul.
            ->not->toContain('confirm(')
            ->toContain('<KonfirmasiDialog bind:permintaan={konfirmasi} />')
            ->toContain('konfirmasi = {');
    }
});

test('dialog konfirmasi punya tombol batal dan pelaksana yang bisa diberi label', function () {
    $dialog = file_get_contents(resource_path('js/components/KonfirmasiDialog.svelte'));

    expect($dialog)
        ->toContain('Batal')
        ->toContain("permintaan.label ?? 'Hapus'")
        // Aksi dijalankan setelah dialognya ditutup supaya tidak terkirim dua kali.
        ->toContain('permintaan = null;')
        ->toContain('aksi?.();')
        // Tindakan tak merusak boleh memakai tombol biasa.
        ->toContain("destruktif ? 'destructive' : 'default'");
});
