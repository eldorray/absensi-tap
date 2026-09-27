<?php

use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;

test('pesan validasi tampil dalam bahasa indonesia', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('izin.store'), [])
        ->assertSessionHasErrors(['tanggal_mulai' => 'Tanggal mulai wajib diisi.']);
});

test('halaman tidak ditemukan memakai halaman error berbahasa indonesia', function () {
    config(['app.debug' => false]);

    $this->get('/alamat-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

test('akses terlarang memakai halaman error berbahasa indonesia', function () {
    config(['app.debug' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

test('sesi habis dikembalikan ke halaman sebelumnya dengan toast', function () {
    Route::post('/_uji-sesi-habis', fn () => throw new TokenMismatchException)->middleware('web');

    $this->from(route('login'))
        ->post('/_uji-sesi-habis')
        ->assertRedirect(route('login'));

    expect(session(SessionKey::FLASH_DATA))->toMatchArray([
        'toast' => ['type' => 'warning', 'message' => 'Sesi berakhir karena terlalu lama tidak aktif. Silakan coba lagi.'],
    ]);
});

test('halaman offline berbahasa indonesia dan punya tombol coba lagi', function () {
    $offline = file_get_contents(public_path('offline.html'));

    expect($offline)->toContain('<html lang="id">')
        ->toContain('Tidak ada koneksi internet')
        ->toContain('location.reload()')
        ->and(file_get_contents(public_path('sw.js')))->not->toContain("'app-v1'");
});

test('galat server tanpa tempat di form dan koneksi putus ditampilkan sebagai toast', function () {
    $toast = file_get_contents(resource_path('js/lib/flash-toast.ts'));

    expect($toast)->toContain("router.on('error'")
        ->toContain("router.on('networkError'");
});
