<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('aplikasi', absolute: false));
});

test('admin diarahkan langsung ke dashboard admin setelah login', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('orang tua tidak diarahkan ke url intended milik pegawai setelah login', function () {
    $orangTua = User::factory()->orangTua()->create();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $orangTua->email,
        'password' => 'password',
    ])->assertRedirect(route('aplikasi', absolute: false));

    $this->assertAuthenticatedAs($orangTua);
    $this->get(route('aplikasi'))->assertRedirect(route('orang-tua.dashboard'));
});

test('orang tua tetap diarahkan ke url intended di wilayahnya', function () {
    $orangTua = User::factory()->orangTua()->create();

    $this->get(route('orang-tua.dashboard'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $orangTua->email,
        'password' => 'password',
    ])->assertRedirect(route('orang-tua.dashboard'));
});

test('guru tetap diarahkan ke url intended pegawai setelah login', function () {
    $guru = User::factory()->create();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $guru->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    // Batasnya tetap ditegakkan, tapi pemakainya dikembalikan ke form dengan
    // kalimat yang menyebut berapa detik lagi -- bukan halaman 429 telanjang.
    // Lihat penangan TooManyRequestsHttpException di bootstrap/app.php.
    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('rate_limit')
        ->assertSessionHas('retry_after');

    $this->assertGuest();
});
