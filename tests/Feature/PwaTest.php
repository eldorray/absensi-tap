<?php

test('manifest menyatakan mode standalone dan start url dashboard', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/dashboard')
        ->and($manifest['orientation'])->toBe('portrait')
        ->and($manifest['short_name'])->toBe('Absensi');
});

test('halaman memuat meta yang menghilangkan address bar di iOS', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('mobile-web-app-capable', false)
        ->assertSee('apple-mobile-web-app-status-bar-style', false)
        ->assertSee('viewport-fit=cover', false);
});

test('halaman memuat theme color untuk mode terang dan gelap', function () {
    $this->get(route('login'))
        ->assertSee('prefers-color-scheme: light', false)
        ->assertSee('prefers-color-scheme: dark', false);
});

test('service worker tetap hanya menangani GET', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("request.method !== 'GET'")
        ->and($sw)->not->toContain('sync');
});
