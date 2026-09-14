<?php

use App\Models\User;

test('sidebar admin memuat kelompok kesiswaan', function () {
    $isi = file_get_contents(resource_path('js/components/AppSidebar.svelte'));

    expect($isi)->toContain('kesiswaanNavItems')
        ->and($isi)->toContain('label="Kesiswaan"')
        ->and($isi)->toContain('@/routes/admin/siswa')
        ->and($isi)->toContain('@/routes/admin/kelas');
});

test('form kesiswaan memakai modal dan daftar memakai tabel', function () {
    $siswa = file_get_contents(resource_path('js/pages/admin/Siswa.svelte'));
    $kelas = file_get_contents(resource_path('js/pages/admin/Kelas.svelte'));

    expect($siswa)->toContain('<table', 'bind:open={dialogTambah}', 'bind:open={dialogImpor}', 'bind:open={dialogUbah}', 'Object.values(impor.errors)');
    expect($kelas)->toContain('<table', 'bind:open={dialogKelas}', 'bind:open={dialogAnggota}', 'bind:open={dialogPengganti}', 'Object.values(tambah.errors)');
});

test('halaman siswa dan kelas terjangkau admin', function () {
    tahunAjaranAktif('2026/2027');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.siswa.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.kelas.index'))->assertOk();
});
