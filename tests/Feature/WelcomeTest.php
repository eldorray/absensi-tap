<?php

use App\Models\PengaturanAplikasi;
use App\Models\User;

test('tamu melihat halaman welcome', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Welcome'));
});

test('halaman welcome memakai nama dan logo dari pengaturan', function () {
    PengaturanAplikasi::current()->update(['nama' => 'Absensi MI Syekh Yusuf']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('aplikasi.nama', 'Absensi MI Syekh Yusuf'));
});

test('welcome mengarahkan tamu ke login dan pengguna yang sudah masuk ke aplikasi', function () {
    $halaman = file_get_contents(resource_path('js/pages/Welcome.svelte'));

    // Satu tombol yang sama berganti tujuan, bukan dua tombol berbeda.
    expect($halaman)
        ->toContain('auth.user ? dashboard() : login()')
        ->toContain('Masuk untuk absen')
        ->toContain('Buka aplikasi')
        ->toContain('Siap untuk tap masuk dan')
        ->toContain('pulang')
        ->toContain('Lokasi • Biometrik • Perangkat')
        // Pendaftaran mandiri dimatikan, jadi tidak boleh ada ajakan daftar.
        ->not->toContain('Daftar')
        ->not->toContain('register');
});

test('welcome menjelaskan tiga syarat absen tanpa mengarang bukti', function () {
    $halaman = file_get_contents(resource_path('js/pages/Welcome.svelte'));

    expect($halaman)
        ->toContain('Berada di halaman sekolah')
        ->toContain('Sidik jari kamu sendiri')
        ->toContain('Satu HP yang sudah disetujui')
        // Tanpa klaim yang tidak bisa dibuktikan.
        ->not->toContain('sekolah mempercayai')
        ->not->toContain('Trusted')
        ->not->toContain('testimoni');
});

test('welcome tidak memuat aset berat maupun sisa starter kit', function () {
    $halaman = file_get_contents(resource_path('js/pages/Welcome.svelte'));

    // HP lawas adalah perangkat acuan: tanpa gambar dekoratif, tanpa font
    // tambahan, dan hanya satu momen gerak yang menghormati reduce motion.
    expect($halaman)
        ->not->toContain('svelte-starter-kit')
        ->not->toContain('laravel.com/docs')
        ->not->toContain('<img src="http')
        ->toContain('prefers-reduced-motion');
});

test('pengguna yang sudah masuk tetap bisa membuka welcome', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Welcome'));
});

test('welcome tersusun untuk HP dan PWA terpasang', function () {
    $halaman = file_get_contents(resource_path('js/pages/Welcome.svelte'));

    expect($halaman)
        ->toContain('min-height: 100dvh')
        ->toContain('safe-top')
        ->toContain('safe-bottom');

    // Warna bilah sistem PWA ikut hijau aplikasi, bukan putih bawaan.
    expect(file_get_contents(public_path('manifest.webmanifest')))
        ->toContain('"theme_color": "#2f6d21"');

    expect(file_get_contents(resource_path('views/app.blade.php')))
        ->toContain('content="#2f6d21"')
        ->toContain('viewport-fit=cover');
});

test('welcome menawarkan instalasi pwa untuk android dan iphone', function () {
    $halaman = file_get_contents(resource_path('js/pages/Welcome.svelte'));
    $banner = file_get_contents(resource_path('js/components/InstallPrompt.svelte'));

    expect($halaman)->toContain('<InstallPrompt />');

    expect($banner)
        ->toContain('beforeinstallprompt')
        ->toContain('Pasang di Android')
        ->toContain('Tambahkan di iPhone')
        ->toContain('Tambahkan ke Layar Utama')
        ->toContain('display-mode: standalone');
});

test('welcome menyediakan tombol pergantian mode di samping tombol masuk', function () {
    $halaman = file_get_contents(resource_path('js/pages/Welcome.svelte'));

    expect($halaman)
        ->toContain('<ThemeToggle />')
        ->toContain('class="header-actions"');
});
