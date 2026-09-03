<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import type { PasskeyError } from '@laravel/passkeys';
    import { usePasskeyVerify } from '@laravel/passkeys/svelte';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import { store as tapAbsensi } from '@/actions/App/Http/Controllers/AbsensiController';
    import {
        index as passkeyOptions,
        store as passkeyVerifyRoute,
    } from '@/actions/App/Http/Controllers/AbsensiPasskeyController';
    import InputError from '@/components/InputError.svelte';
    import { Spinner } from '@/components/ui/spinner';

    type Props = {
        tipe: 'masuk' | 'pulang';
        label: string;
        punyaPasskey: boolean;
        deviceUuid: string | null;
        disabled?: boolean;
    };

    let {
        tipe,
        label,
        punyaPasskey,
        deviceUuid,
        disabled = false,
    }: Props = $props();

    let sedangProses = $state(false);
    let pesanGalat = $state('');
    let posisi: GeolocationPosition | null = null;

    const passkeyVerify = usePasskeyVerify({
        routes: {
            options: passkeyOptions.url(),
            submit: passkeyVerifyRoute.url(),
        },
        onSuccess: () => kirim(),
        onError: (galat: PasskeyError) => {
            pesanGalat = galat.message || 'Verifikasi sidik jari gagal.';
            sedangProses = false;
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
            sedangProses = false;

            return;
        }

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
                onError: (errors: Record<string, string>) => {
                    pesanGalat = errors.tap ?? 'Absen gagal. Coba lagi.';
                },
                onFinish: () => {
                    sedangProses = false;
                },
            },
        );
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

        sedangProses = true;

        try {
            posisi = await ambilPosisi();
        } catch (galat) {
            sedangProses = false;
            pesanGalat = pesanLokasi(galat);

            return;
        }

        if (punyaPasskey) {
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
        type="button"
        class="tap"
        onclick={tap}
        disabled={disabled || sedangProses}
    >
        {#if sedangProses}
            <Spinner />
            Membaca lokasi...
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
        <InputError message={pesanGalat} />
    {/if}
</div>

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
