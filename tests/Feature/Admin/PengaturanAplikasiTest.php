<?php

use App\Models\PengaturanAplikasi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('guru tidak bisa mengubah identitas aplikasi', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.aplikasi.update'), ['nama' => 'Bajakan'])
        ->assertForbidden();

    expect(PengaturanAplikasi::current()->nama)->toBe('Absensi Guru');
});

test('admin mengubah nama aplikasi', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.aplikasi.update'), ['nama' => 'Absensi SMP Syekh Yusuf'])
        ->assertRedirect(route('admin.pengaturan.edit'));

    expect(PengaturanAplikasi::current()->nama)->toBe('Absensi SMP Syekh Yusuf');
});

test('admin mengunggah logo dan favicon', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.aplikasi.update'), [
            'nama' => 'Absensi Guru',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])
        ->assertRedirect();

    $aplikasi = PengaturanAplikasi::current();

    expect($aplikasi->logo_path)->not->toBeNull()
        ->and($aplikasi->favicon_path)->not->toBeNull();

    Storage::disk('public')->assertExists($aplikasi->logo_path);
    Storage::disk('public')->assertExists($aplikasi->favicon_path);
});

test('logo lama dihapus saat diganti', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.aplikasi.update'), [
        'nama' => 'Absensi Guru',
        'logo' => UploadedFile::fake()->image('lama.png'),
    ]);
    $lama = PengaturanAplikasi::current()->logo_path;

    $this->actingAs($admin)->post(route('admin.aplikasi.update'), [
        'nama' => 'Absensi Guru',
        'logo' => UploadedFile::fake()->image('baru.png'),
    ]);
    $baru = PengaturanAplikasi::current()->fresh()->logo_path;

    expect($baru)->not->toBe($lama);
    Storage::disk('public')->assertMissing($lama);
    Storage::disk('public')->assertExists($baru);
});

test('svg dan berkas kelewat besar ditolak', function () {
    $admin = User::factory()->admin()->create();

    // SVG bisa memuat <script> dan dilayani dari domain aplikasi.
    $this->actingAs($admin)->post(route('admin.aplikasi.update'), [
        'nama' => 'Absensi Guru',
        'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
    ])->assertSessionHasErrors('logo');

    $this->actingAs($admin)->post(route('admin.aplikasi.update'), [
        'nama' => 'Absensi Guru',
        'logo' => UploadedFile::fake()->image('besar.png')->size(600),
    ])->assertSessionHasErrors('logo');

    expect(PengaturanAplikasi::current()->logo_path)->toBeNull();
});

test('nama wajib diisi dan dibatasi panjangnya', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.aplikasi.update'), ['nama' => ''])
        ->assertSessionHasErrors('nama');
    $this->actingAs($admin)->post(route('admin.aplikasi.update'), ['nama' => str_repeat('a', 61)])
        ->assertSessionHasErrors('nama');
});

test('nama dan logo dibagikan ke semua halaman', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.aplikasi.update'), [
        'nama' => 'Absensi MI',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.pengaturan.edit'))
        ->assertInertia(fn ($p) => $p
            ->where('name', 'Absensi MI')
            ->where('aplikasi.nama', 'Absensi MI')
            ->has('aplikasi.logo_url')
            ->where('aplikasi.nama', 'Absensi MI'));
});

test('favicon dan judul di root template mengikuti pengaturan', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.aplikasi.update'), [
        'nama' => 'Absensi MI',
        'favicon' => UploadedFile::fake()->image('favicon.png'),
    ]);

    $favicon = PengaturanAplikasi::current()->faviconUrl();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        // Judul tab dan ikon di root template, bukan dari config app.name.
        ->assertSee('content="Absensi MI"', false)
        ->assertSee($favicon, false);
});

test('logo dan nama aplikasi dipakai di halaman login', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.aplikasi.update'), [
            'nama' => 'Absensi MI',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

    $logo = PengaturanAplikasi::current()->logoUrl();

    // Keluar dulu: /login mengalihkan pengguna yang sudah masuk.
    auth()->logout();

    // Halaman login tamu ikut menerima prop identitas.
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('auth/Login')
            ->where('aplikasi.nama', 'Absensi MI')
            ->where('aplikasi.logo_url', $logo));

    // Layout auth memakainya, dengan ikon bawaan sebagai cadangan.
    expect(file_get_contents(resource_path('js/layouts/auth/AuthSimpleLayout.svelte')))
        ->toContain('aplikasi?.logo_url')
        ->toContain('<AppLogoIcon')
        ->toContain('aplikasi?.nama ?? page.props.name');
});
