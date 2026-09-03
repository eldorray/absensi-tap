const KUNCI = 'absensi.device_uuid';

/**
 * Baca uuid perangkat. localStorage lebih dulu; kalau kosong, pakai nilai
 * cadangan dari cookie yang dikirim server dan tulis ulang ke localStorage.
 *
 * Dua penyimpanan harus hilang bersamaan sebelum guru terlihat sebagai
 * perangkat baru. Cookie bukan lapisan keamanan -- nilainya sama persis.
 */
export function bacaDeviceUuid(cadangan: string | null): string | null {
    try {
        const tersimpan = localStorage.getItem(KUNCI);

        if (tersimpan) {
            return tersimpan;
        }
    } catch {
        // Safari private mode melempar begitu localStorage disentuh.
    }

    if (cadangan) {
        simpanDeviceUuid(cadangan);

        return cadangan;
    }

    return null;
}

export function simpanDeviceUuid(uuid: string): void {
    try {
        localStorage.setItem(KUNCI, uuid);
    } catch {
        // Tidak fatal: cookie dari server tetap jadi cadangan.
    }
}

/**
 * crypto.randomUUID hanya ada di secure context. Aplikasi ini memang wajib
 * HTTPS karena geolocation, jadi tidak ada fallback yang perlu ditulis.
 */
export function buatDeviceUuid(): string {
    return crypto.randomUUID();
}
