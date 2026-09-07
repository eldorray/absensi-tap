<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('halaman login tidak menawarkan passkey dan pendaftaran sendiri', function () {
    $login = file_get_contents(resource_path('js/pages/auth/Login.svelte'));

    expect($login)
        ->toContain('data-test="login-button"')
        ->not->toContain('PasskeyVerify')
        ->not->toContain('Sign up')
        ->not->toContain('register')
        // Seluruh antarmuka Bahasa Indonesia.
        ->toContain("title: 'Masuk'")
        ->toContain('Lupa password?')
        ->toContain('Ingat saya di HP ini')
        ->toContain('Akses khusus warga sekolah')
        ->toContain('Kembali ke halaman depan')
        ->not->toContain('Remember me')
        ->not->toContain('Email address');

    // Layout auth memikul identitas dan janji produk di pita hijau, dan
    // menyusut jadi kop tipis di layar sempit supaya form yang berkuasa.
    $layout = file_get_contents(resource_path('js/layouts/auth/AuthSimpleLayout.svelte'));

    expect($layout)
        ->toContain('var(--g-band)')
        // Bertumpu HP: layar penuh tanpa kartu mengambang, aman dari notch dan
        // home indicator, dan tinggi mengikuti dvh karena bilah alamat Android
        // muncul-hilang.
        ->toContain('min-height: 100dvh')
        ->toContain('env(safe-area-inset-top)')
        ->toContain('env(safe-area-inset-bottom)')
        ->toContain('@media (min-width: 900px)')
        // Tanpa penjaga ini satu anak yang kelebaran menggeser seluruh layar
        // dan memotong sisi kiri pita.
        ->toContain('overflow-x: hidden')
        ->toContain('min-width: 0')
        // Bukti dibungkus jadi pil, bukan baris geser yang ujungnya terpotong.
        ->toContain('flex-wrap: wrap')
        ->not->toContain('overflow-x: auto')
        ->toContain('aplikasi?.logo_url')
        ->toContain('aplikasi?.nama ?? page.props.name')
        ->toContain('<InstallPrompt />');
});

test('route pendaftaran sendiri tidak tersedia', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Orang Asing',
        'email' => 'asing@example.com',
        'password' => 'RahasiaKuat123',
        'password_confirmation' => 'RahasiaKuat123',
    ])->assertNotFound();

    expect(User::where('email', 'asing@example.com')->exists())->toBeFalse();
});
