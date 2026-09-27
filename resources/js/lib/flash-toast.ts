import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Galat yang sudah punya tempat tampil sendiri di halamannya, jadi tidak
 * perlu diulang sebagai toast. 'tap' tampil di bawah tombol absen.
 */
const DITAMPILKAN_HALAMAN = ['tap'];

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    initializeGalatGlobal();
}

/**
 * Tampilkan galat server yang tidak punya tempat di form sebagai toast.
 *
 * Galat validasi field (kunci yang ikut dikirim, mis. "judul" atau
 * "jadwals.0.jam_masuk") sudah tampil di bawah input-nya. Yang tersisa --
 * aturan bisnis seperti "admin aktif terakhir", "sudah direview", atau batas
 * percobaan (rate_limit) -- sebelumnya hilang tanpa jejak, sehingga tombol
 * terlihat tidak bereaksi.
 */
function initializeGalatGlobal(): void {
    let kunciTerkirim = new Set<string>();

    router.on('start', (event) => {
        const { data, url } = event.detail.visit;
        const kunci =
            data instanceof FormData
                ? [...data.keys()].map((nama) => nama.split('[')[0])
                : data && typeof data === 'object'
                  ? Object.keys(data)
                  : [];

        // Kunjungan GET memindahkan data ke query string.
        kunciTerkirim = new Set([...kunci, ...url.searchParams.keys()]);
    });

    router.on('error', (event) => {
        // Halaman masuk dan sejenisnya menampilkan semua galatnya sendiri.
        if (event.detail.page?.component.startsWith('auth/')) {
            return;
        }

        for (const [kunci, pesan] of Object.entries(event.detail.errors)) {
            const induk = kunci.split('.')[0];

            if (
                typeof pesan !== 'string' ||
                kunciTerkirim.has(induk) ||
                DITAMPILKAN_HALAMAN.includes(kunci)
            ) {
                continue;
            }

            toast.error(pesan);
        }
    });

    router.on('networkError', () => {
        toast.error(
            'Tidak ada koneksi internet. Periksa sinyal, lalu coba lagi.',
        );
    });
}
