<?php

use App\Models\Lokasi;
use App\Models\Perangkat;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seorang guru dengan HP terikat, satu lokasi absen aktif, dan jadwal kerja terisi.
 *
 * @return array{0: User, 1: Perangkat, 2: Lokasi}
 */
function guruSiapAbsen(): array
{
    app(JadwalKerjaSeeder::class)->run();

    $guru = User::factory()->create();

    return [
        $guru,
        Perangkat::factory()->for($guru)->create(),
        Lokasi::factory()->create(),
    ];
}

/**
 * Daftarkan satu passkey palsu supaya hasPasskeysEnabled() bernilai true.
 *
 * Kredensialnya tidak sah untuk upacara WebAuthn -- itu memang tidak diperlukan:
 * yang diuji di sini adalah gerbang "punya passkey wajib verifikasi", bukan
 * validasi tanda tangan.
 */
function pasangPasskeyPalsu(User $guru): void
{
    Passkey::forceCreate([
        'user_id' => $guru->id,
        'name' => 'HP Guru',
        'credential_id' => 'kredensial-uji-'.$guru->id,
        'credential' => [],
    ]);
}
