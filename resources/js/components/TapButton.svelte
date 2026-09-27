<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import type { PasskeyError } from '@laravel/passkeys';
    import { usePasskeyVerify } from '@laravel/passkeys/svelte';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import { store as tapAbsensi } from '@/actions/App/Http/Controllers/AbsensiController';
    import {
        index as passkeyOptions,
        store as passkeyVerifyRoute,
    } from '@/actions/App/Http/Controllers/AbsensiPasskeyController';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import { Spinner } from '@/components/ui/spinner';
    import { letupan } from '@/lib/letupan';

    type Props = {
        tipe: 'masuk' | 'pulang';
        label: string;
        punyaPasskey: boolean;
        deviceUuid: string | null;
        disabled?: boolean;
        /** Jam pulang terjadwal, kalau tap sekarang akan tercatat pulang cepat. */
        pulangCepatSebelum?: string | null;
    };

    let {
        tipe,
        label,
        punyaPasskey,
        deviceUuid,
        disabled = false,
        pulangCepatSebelum = null,
    }: Props = $props();

    /**
     * Tahap yang sedang berjalan. Label tombol mengikutinya, jadi guru tahu
     * sedang menunggu GPS, sidik jari, atau server -- bukan "membaca lokasi"
     * terus-menerus.
     */
    let tahap = $state<'lokasi' | 'verifikasi' | 'kirim' | null>(null);
    const sedangProses = $derived(tahap !== null);
    const labelTahap = {
        lokasi: 'Membaca lokasi…',
        verifikasi: 'Verifikasi sidik jari…',
        kirim: 'Mengirim…',
    } as const;
    let konfirmasi = $state<Konfirmasi | null>(null);
    let pesanGalat = $state('');
    let posisi: GeolocationPosition | null = null;
    let tombol = $state<HTMLButtonElement | null>(null);

    const passkeyVerify = usePasskeyVerify({
        routes: {
            options: passkeyOptions.url(),
            submit: passkeyVerifyRoute.url(),
        },
        onSuccess: () => kirim(),
        onError: (galat: PasskeyError) => {
            // Pesan pustaka passkey berbahasa Inggris dan teknis.
            pesanGalat = /cancel|abort|not ?allowed/i.test(galat.message ?? '')
                ? 'Verifikasi sidik jari dibatalkan. Tap lagi untuk mencoba.'
                : 'Verifikasi sidik jari gagal. Coba lagi, atau hubungi TU kalau terus gagal.';
            tahap = null;
        },
    });

    function ambilPosisi(): Promise<GeolocationPosition> {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10_000,
                maximumAge: 0,
            });
        });
    }

    function kirim(): void {
        if (!posisi || !deviceUuid) {
            tahap = null;

            return;
        }

        tahap = 'kirim';

        // Dicatat sebelum kirim: setelah berhasil, halaman memuat ulang props
        // dan tombol ini bisa sudah hilang saat onSuccess berjalan.
        const kotak = tombol?.getBoundingClientRect();

        router.post(
            tapAbsensi.url(),
            {
                tipe,
                latitude: posisi.coords.latitude,
                longitude: posisi.coords.longitude,
                accuracy: Math.round(posisi.coords.accuracy),
                device_uuid: deviceUuid,
            },
            {
                preserveScroll: true,
                onSuccess: () => rayakan(kotak),
                onError: (errors: Record<string, string>) => {
                    pesanGalat =
                        errors.rate_limit ??
                        errors.tap ??
                        'Absen gagal. Coba lagi.';
                },
                // false: pesan tampil di sini, bukan toast global atau modal.
                onNetworkError: () => {
                    pesanGalat =
                        'Koneksi terputus, absen belum tercatat. Periksa sinyal lalu tap lagi.';

                    return false;
                },
                onHttpException: () => {
                    pesanGalat =
                        'Server sedang bermasalah, absen belum tercatat. Coba lagi sebentar lagi.';

                    return false;
                },
                onFinish: () => {
                    tahap = null;
                },
            },
        );
    }

    /** Percobaan001: letupan dari tengah tombol, hanya saat absen berhasil. */
    function rayakan(kotak: DOMRect | undefined): void {
        if (!page.props.percobaan?.percobaan001 || kotak === undefined) {
            return;
        }

        letupan(kotak.left + kotak.width / 2, kotak.top + kotak.height / 2);
    }

    function tekan(): void {
        if (pulangCepatSebelum) {
            konfirmasi = {
                judul: 'Belum jam pulang',
                pesan: `Jam pulangmu ${pulangCepatSebelum}. Kalau tetap absen sekarang, akan tercatat pulang cepat.`,
                label: 'Tetap absen pulang',
                destruktif: false,
                aksi: () => void tap(),
            };

            return;
        }

        void tap();
    }

    async function tap(): Promise<void> {
        pesanGalat = '';

        if (!navigator.onLine) {
            pesanGalat = 'Butuh koneksi internet untuk absen.';

            return;
        }

        if (!deviceUuid) {
            pesanGalat = 'HP ini belum terdaftar. Muat ulang halaman.';

            return;
        }

        if (punyaPasskey && !passkeyVerify.isSupported) {
            pesanGalat =
                'Browser di HP ini tidak mendukung verifikasi sidik jari. Buka lewat Chrome atau Safari terbaru, atau hubungi TU.';

            return;
        }

        tahap = 'lokasi';

        try {
            posisi = await ambilPosisi();
        } catch (galat) {
            tahap = null;
            pesanGalat = pesanLokasi(galat);

            return;
        }

        if (punyaPasskey) {
            tahap = 'verifikasi';
            // onSuccess akan memanggil kirim().
            passkeyVerify.verify();

            return;
        }

        kirim();
    }

    function pesanLokasi(galat: unknown): string {
        const kode = (galat as GeolocationPositionError | undefined)?.code;

        if (kode === 1) {
            return 'Izin lokasi ditolak. Buka Pengaturan aplikasi, aktifkan Lokasi dan Lokasi Tepat.';
        }

        if (kode === 3) {
            return 'GPS terlalu lama merespons. Coba di luar ruangan.';
        }

        return 'Lokasi tidak terbaca. Aktifkan GPS lalu coba lagi.';
    }
</script>

<div class="grid gap-3">
    <button
        bind:this={tombol}
        type="button"
        class="tap"
        onclick={tekan}
        disabled={disabled || sedangProses}
    >
        {#if tahap}
            <Spinner />
            {labelTahap[tahap]}
        {:else}
            {#if punyaPasskey}
                <Fingerprint class="size-6" aria-hidden="true" />
            {:else}
                <MapPin class="size-6" aria-hidden="true" />
            {/if}
            {label}
        {/if}
    </button>

    {#if pesanGalat}
        <p
            class="flex items-start gap-2 rounded-2xl bg-[var(--g-red-c)] px-4 py-3 text-sm font-medium text-[var(--g-red-ink)]"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {pesanGalat}
        </p>
    {/if}
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />

<style>
    .tap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        width: 100%;
        min-height: 5.5rem;
        border-radius: 28px;
        background: linear-gradient(
            115deg,
            var(--g-lime) 0%,
            var(--g-lime-2) 100%
        );
        color: var(--g-lime-ink);
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        /* Cegah double-tap zoom, seleksi teks, dan kilatan tap di mobile. */
        touch-action: manipulation;
        user-select: none;
        transition:
            border-radius 0.5s var(--g-emphasized),
            transform 0.3s var(--g-emphasized);
    }

    .tap:active {
        transform: scale(0.98);
    }

    .tap:hover:not(:disabled) {
        border-radius: 56px 28px 56px 28px;
    }

    .tap:disabled {
        opacity: 0.55;
    }
</style>
