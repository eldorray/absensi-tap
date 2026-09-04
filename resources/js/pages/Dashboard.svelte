<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Absensi', href: dashboard() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import { store as daftarkanPerangkat } from '@/actions/App/Http/Controllers/PerangkatController';
    import AppHead from '@/components/AppHead.svelte';
    import InstallPrompt from '@/components/InstallPrompt.svelte';
    import TapButton from '@/components/TapButton.svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        bacaDeviceUuid,
        buatDeviceUuid,
        simpanDeviceUuid,
    } from '@/lib/perangkat';

    type Jadwal = {
        jam_masuk: string;
        jam_pulang: string;
        toleransi_menit: number;
        is_hari_kerja: boolean;
    };

    type Hari = {
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
        terverifikasi: boolean;
    };

    type Riwayat = {
        tanggal: string;
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
    };

    let {
        jadwal,
        hariIni,
        namaLokasi,
        punyaPasskey,
        perangkatUuidTersimpan,
        riwayat,
    }: {
        jadwal: Jadwal | null;
        hariIni: Hari | null;
        namaLokasi: string | null;
        punyaPasskey: boolean;
        perangkatUuidTersimpan: string | null;
        riwayat: Riwayat[];
    } = $props();

    let deviceUuid = $state<string | null>(null);
    let jam = $state(waktuSekarang());

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const tanggalPendek = new Intl.DateTimeFormat('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

    function waktuSekarang(): string {
        return new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(new Date());
    }

    $effect(() => {
        const jeda = setInterval(() => {
            jam = waktuSekarang();
        }, 1000);

        return () => clearInterval(jeda);
    });

    $effect(() => {
        const tersimpan = bacaDeviceUuid(perangkatUuidTersimpan);

        if (tersimpan) {
            deviceUuid = tersimpan;

            return;
        }

        const baru = buatDeviceUuid();
        simpanDeviceUuid(baru);
        deviceUuid = baru;

        router.post(
            daftarkanPerangkat.url(),
            { device_uuid: baru },
            { preserveScroll: true },
        );
    });

    const sudahMasuk = $derived(hariIni?.jam_masuk != null);
    const sudahPulang = $derived(hariIni?.jam_pulang != null);
    const selesai = $derived(sudahMasuk && sudahPulang);

    const labelStatus: Record<string, string> = {
        hadir: 'Hadir',
        terlambat: 'Terlambat',
    };

    function labelRiwayat(baris: Riwayat): string {
        const dasar = baris.status
            ? (labelStatus[baris.status] ?? baris.status)
            : '-';

        return baris.pulang_cepat ? `${dasar} · pulang cepat` : dasar;
    }
</script>

<AppHead title="Absensi" />

<div
    class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <InstallPrompt />

    <section class="g-tile g-tone-plain">
        <p
            class="text-xs font-semibold tracking-[0.14em] uppercase text-muted-foreground"
        >
            {tanggalPanjang.format(new Date())}
        </p>
        <p
            class="g-display text-[clamp(2.5rem,10vw,3.5rem)] font-mono tabular-nums"
        >
            {jam}
        </p>

        {#if jadwal && jadwal.is_hari_kerja}
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <CalendarClock class="size-4" aria-hidden="true" />
                Masuk {jadwal.jam_masuk} · Pulang {jadwal.jam_pulang} · Toleransi
                {jadwal.toleransi_menit} menit
            </p>
        {:else}
            <p class="text-sm text-muted-foreground">
                Hari ini bukan hari kerja.
            </p>
        {/if}

        {#if namaLokasi}
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <MapPin class="size-4" aria-hidden="true" />
                Absen hanya di sekitar {namaLokasi}
            </p>
        {/if}
    </section>

    <section class="g-tile {sudahMasuk ? 'g-tone-green' : 'g-tone-yellow'}">
        <h3>Status hari ini</h3>

        {#if hariIni}
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">
                    {hariIni.status
                        ? (labelStatus[hariIni.status] ?? hariIni.status)
                        : 'Tercatat'}
                </Badge>
                {#if hariIni.pulang_cepat}
                    <Badge variant="outline">Pulang cepat</Badge>
                {/if}
                {#if !hariIni.terverifikasi}
                    <Badge variant="outline" class="gap-1">
                        <ShieldAlert class="size-3" aria-hidden="true" />
                        Tanpa biometrik
                    </Badge>
                {/if}
            </div>
            <p>
                Masuk {hariIni.jam_masuk ?? '-'} · Pulang {hariIni.jam_pulang ??
                    '-'}
            </p>
        {:else}
            <p>Belum ada absen hari ini.</p>
        {/if}
    </section>

    {#if selesai}
        <p
            class="flex items-center justify-center gap-2 py-2 text-sm text-muted-foreground"
        >
            <CircleCheck class="size-4" aria-hidden="true" />
            Absen hari ini sudah lengkap.
        </p>
    {:else}
        <TapButton
            tipe={sudahMasuk ? 'pulang' : 'masuk'}
            label={sudahMasuk ? 'TAP PULANG' : 'TAP MASUK'}
            {punyaPasskey}
            {deviceUuid}
            disabled={deviceUuid === null}
        />
    {/if}

    <section class="g-tile g-tone-plain">
        <h3>Riwayat 30 hari</h3>

        {#if riwayat.length === 0}
            <p>Belum ada riwayat absen.</p>
        {:else}
            <ul class="divide-y divide-border">
                {#each riwayat as baris (baris.tanggal)}
                    <li
                        class="flex items-center justify-between gap-4 py-2.5 text-sm"
                    >
                        <span class="font-medium">
                            {tanggalPendek.format(new Date(baris.tanggal))}
                        </span>
                        <span class="text-muted-foreground">
                            {baris.jam_masuk ?? '-'} – {baris.jam_pulang ?? '-'}
                        </span>
                        <span class="font-medium">{labelRiwayat(baris)}</span>
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
