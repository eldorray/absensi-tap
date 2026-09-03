<?php

use App\Support\Jarak;

test('titik yang sama berjarak nol meter', function () {
    expect(Jarak::meter(-6.2088, 106.8456, -6.2088, 106.8456))->toBe(0);
});

test('satu derajat lintang kurang lebih 111 kilometer', function () {
    expect(Jarak::meter(0.0, 0.0, 1.0, 0.0))
        ->toBeGreaterThan(111_100)
        ->toBeLessThan(111_400);
});

test('Monas ke Kota Tua kurang lebih 4,7 kilometer', function () {
    expect(Jarak::meter(-6.175392, 106.827153, -6.135200, 106.813309))
        ->toBeGreaterThan(4_300)
        ->toBeLessThan(4_800);
});

test('titik antipodal tidak memicu NaN', function () {
    expect(Jarak::meter(0.0, 0.0, 0.0, 180.0))->toBeGreaterThan(20_000_000);
});
