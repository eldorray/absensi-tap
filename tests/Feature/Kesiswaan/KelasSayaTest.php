<?php

use App\Models\AnggotaKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
});

test('wali kelas melihat menu dan kelas yang diampunya', function () {
    $guru = User::factory()->create();
    $kantor = Kantor::factory()->create();
    $kelas = Kelas::factory()->create([
        'kantor_id' => $kantor->id,
        'wali_kelas_id' => $guru->id,
        'nama' => '5A',
    ]);
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    AnggotaKelas::factory()->create(['kelas_id' => $kelas->id, 'siswa_id' => $siswa->id]);

    $this->actingAs($guru)
        ->get(route('kelas-saya.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kelas-saya/Index')
            ->where('kelas.0.nama', '5A')
            ->where('kelas.0.anggotas.0.nama', $siswa->nama));

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.punyaKelas', true));
});

test('guru tanpa kelas tidak memperoleh data kelas', function () {
    $guru = User::factory()->create();

    $this->actingAs($guru)
        ->get(route('kelas-saya.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kelas', []));
});

test('navigasi guru memakai empat menu utama dan popup lainnya', function () {
    $navigasi = file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte'));

    expect($navigasi)
        ->toContain("label: 'Absensi'")
        ->toContain("label: 'Izin'")
        ->toContain("label: 'Jadwal'")
        ->not->toContain("label: 'Jadwal Saya'")
        ->toContain('aria-label="Buka menu lainnya"')
        ->toContain('Riwayat')
        ->toContain('Kelas Saya')
        ->toContain('Absensi Siswa')
        ->toContain('page.props.auth.punyaKelas')
        ->toContain('@/routes/kelas-saya');
});
