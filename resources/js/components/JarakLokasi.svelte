<script lang="ts">
    import MapPin from 'lucide-svelte/icons/map-pin';
    import RotateCw from 'lucide-svelte/icons/rotate-cw';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import { jarakMeter } from '@/lib/jarak';

    /** Sama dengan CatatAbsensi::AKURASI_MAKSIMAL_METER. */
    const AKURASI_MAKSIMAL_METER = 75;

    type Lokasi = {
        id: number;
        nama: string;
        latitude: number;
        longitude: number;
        radius_meter: number;
    };

    let { lokasis }: { lokasis: Lokasi[] } = $props();

    let posisi = $state<GeolocationPosition | null>(null);
    let galat = $state('');
    let sedangCari = $state(false);

    /**
     * Lokasi terdekat beserta jaraknya, mengikuti cara server memilih: lokasi
     * aktif dengan jarak terkecil dari koordinat guru.
     */
    const terdekat = $derived.by(() => {
        if (posisi === null || lokasis.length === 0) {
            return null;
        }

        const { latitude, longitude } = posisi.coords;

        return lokasis
            .map((lokasi) => ({
                lokasi,
                jarak: jarakMeter(
                    latitude,
                    longitude,
                    Number(lokasi.latitude),
                    Number(lokasi.longitude),
                ),
            }))
            .sort((a, b) => a.jarak - b.jarak)[0];
    });

    const diDalam = $derived(
        terdekat !== null && terdekat.jarak <= terdekat.lokasi.radius_meter,
    );

    const akurasi = $derived(
        posisi === null ? null : Math.round(posisi.coords.accuracy),
    );

    function cariPosisi(): void {
        if (!('geolocation' in navigator)) {
            galat = 'Perangkat ini tidak mendukung GPS.';

            return;
        }

        sedangCari = true;
        galat = '';

        navigator.geolocation.getCurrentPosition(
            (hasil) => {
                posisi = hasil;
                sedangCari = false;
            },
            (kesalahan) => {
                galat = pesanGalat(kesalahan);
                sedangCari = false;
            },
            { enableHighAccuracy: true, timeout: 10_000, maximumAge: 30_000 },
        );
    }

    function pesanGalat(kesalahan: GeolocationPositionError): string {
        return kesalahan.code === kesalahan.PERMISSION_DENIED
            ? 'Izin lokasi ditolak. Buka pengaturan browser atau aplikasi, lalu aktifkan Lokasi dan Lokasi Tepat.'
            : 'Lokasi belum terbaca. Coba di luar ruangan.';
    }

    /*
     * Jarak diperbarui terus selama halaman terlihat, supaya guru yang sedang
     * berjalan masuk area sekolah melihat angkanya turun tanpa menekan
     * refresh. Pemantauan dihentikan saat halaman di background agar hemat
     * baterai.
     */
    $effect(() => {
        cariPosisi();

        if (!('geolocation' in navigator)) {
            return;
        }

        let pantau: number | null = null;

        const mulai = (): void => {
            if (pantau !== null || document.hidden) {
                return;
            }

            pantau = navigator.geolocation.watchPosition(
                (hasil) => {
                    posisi = hasil;
                    galat = '';
                },
                (kesalahan) => {
                    // Gagal sesaat saat memantau tidak menimpa posisi terakhir.
                    if (kesalahan.code === kesalahan.PERMISSION_DENIED) {
                        galat = pesanGalat(kesalahan);
                    }
                },
                { enableHighAccuracy: true, maximumAge: 15_000 },
            );
        };

        const berhenti = (): void => {
            if (pantau !== null) {
                navigator.geolocation.clearWatch(pantau);
                pantau = null;
            }
        };

        const saatBerganti = (): void =>
            document.hidden ? berhenti() : mulai();

        mulai();
        document.addEventListener('visibilitychange', saatBerganti);

        return () => {
            berhenti();
            document.removeEventListener('visibilitychange', saatBerganti);
        };
    });
</script>

<div class="grid gap-1">
    <div class="flex items-center gap-2 text-sm">
        <MapPin class="size-4 shrink-0" aria-hidden="true" />

        {#if lokasis.length === 0}
            <span class="flex-1">Belum ada lokasi absen aktif.</span>
        {:else if galat}
            <span class="flex-1">{galat}</span>
        {:else if terdekat === null}
            <span class="flex-1 text-muted-foreground"
                >{sedangCari
                    ? 'Membaca lokasi…'
                    : 'Lokasi belum terbaca.'}</span
            >
        {:else}
            <span class="flex-1">
                <b class="font-mono font-bold tabular-nums"
                    >{terdekat.jarak} m</b
                >
                dari {terdekat.lokasi.nama} ·
                {diDalam ? 'di dalam' : 'di luar'} radius {terdekat.lokasi
                    .radius_meter} m
            </span>
        {/if}

        <button
            type="button"
            onclick={cariPosisi}
            disabled={sedangCari}
            aria-label="Perbarui jarak"
            class="-my-1.5 grid size-11 shrink-0 place-items-center rounded-full transition-colors active:bg-background/60 disabled:opacity-50"
        >
            <RotateCw
                class="size-4 {sedangCari ? 'animate-spin' : ''}"
                aria-hidden="true"
            />
        </button>
    </div>

    {#if terdekat !== null && !diDalam}
        <p class="text-xs">
            Dekati {terdekat.lokasi.nama} sekitar {terdekat.jarak -
                terdekat.lokasi.radius_meter} m lagi supaya tap diterima.
        </p>
    {/if}

    {#if akurasi !== null && akurasi > AKURASI_MAKSIMAL_METER}
        <p class="flex items-start gap-1.5 text-xs">
            <TriangleAlert
                class="mt-0.5 size-3.5 shrink-0"
                aria-hidden="true"
            />
            Sinyal GPS lemah (±{akurasi} m). Tap ditolak di atas {AKURASI_MAKSIMAL_METER}
            m — coba di luar ruangan.
        </p>
    {/if}
</div>
