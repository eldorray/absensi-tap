/** Radius bumi rata-rata dalam meter (IUGG mean radius), sama dengan App\Support\Jarak. */
const RADIUS_BUMI_METER = 6_371_000;

/**
 * Jarak lingkaran besar antara dua koordinat, dibulatkan ke meter.
 *
 * Cerminan App\Support\Jarak::meter() supaya angka yang dilihat guru sama
 * dengan angka yang dipakai server saat menilai tapnya.
 */
export function jarakMeter(
    lat1: number,
    lng1: number,
    lat2: number,
    lng2: number,
): number {
    const rad = Math.PI / 180;
    const deltaLat = (lat2 - lat1) * rad;
    const deltaLng = (lng2 - lng1) * rad;

    const a =
        Math.sin(deltaLat / 2) ** 2 +
        Math.cos(lat1 * rad) *
            Math.cos(lat2 * rad) *
            Math.sin(deltaLng / 2) ** 2;

    return Math.round(
        RADIUS_BUMI_METER * 2 * Math.asin(Math.min(1, Math.sqrt(a))),
    );
}
