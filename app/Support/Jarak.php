<?php

namespace App\Support;

final class Jarak
{
    /**
     * Radius bumi rata-rata dalam meter (IUGG mean radius).
     */
    private const RADIUS_BUMI_METER = 6_371_000.0;

    /**
     * Jarak lingkaran besar antara dua koordinat, dibulatkan ke meter.
     */
    public static function meter(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        // min(1.0, ...) menjaga asin() dari galat pembulatan pada titik antipodal.
        return (int) round(self::RADIUS_BUMI_METER * 2 * asin(min(1.0, sqrt($a))));
    }
}
