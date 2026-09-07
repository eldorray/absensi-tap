<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Absensi', href: dashboard() }],
    };

    /**
     * Uuid yang sudah dikirim ke server sepanjang tab ini hidup.
     *
     * Pendaftarannya membalas redirect ke dashboard, jadi tanpa penjaga ini
     * halaman mount ulang, mengirim lagi, dan berputar tanpa henti.
     */
    let uuidTerkirim: string | null = null;
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import { store as daftarkanPerangkat } from '@/actions/App/Http/Controllers/PerangkatController';
    import AppHead from '@/components/AppHead.svelte';
    import InstallPrompt from '@/components/InstallPrompt.svelte';
    import JarakLokasi from '@/components/JarakLokasi.svelte';
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
        buka_masuk: string;
        tutup_masuk: string;
        buka_pulang: string;
        is_hari_kerja: boolean;
    };

    type Hari = {
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
        terverifikasi: boolean;
    };

    type Pengumuman = { id: number; judul: string; isi: string };

    type Lokasi = {
        id: number;
        nama: string;
        latitude: number;
        longitude: number;
        radius_meter: number;
    };

    let {
        jadwal,
        hariIni,
        pengumumans,
        lokasis,
        punyaPasskey,
        perangkatUuidTersimpan,
        statusPerangkat = null,
    }: {
        jadwal: Jadwal | null;
        hariIni: Hari | null;
        pengumumans: Pengumuman[];
        lokasis: Lokasi[];
        punyaPasskey: boolean;
        perangkatUuidTersimpan: string | null;
        statusPerangkat?: 'pending' | 'active' | 'revoked' | null;
    } = $props();

    let deviceUuid = $state<string | null>(null);
    let jam = $state(waktuSekarang());

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
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
        const uuid = bacaDeviceUuid(perangkatUuidTersimpan) ?? buatDeviceUuid();
        simpanDeviceUuid(uuid);
        deviceUuid = uuid;

        // Didaftarkan hanya kalau server belum mengenal uuid ini -- entah karena
        // baru, atau karena barisnya hilang (dihapus admin, database di-reset).
        // Tanpa cabang ini browser menyimpan uuid tak terdaftar selamanya dan
        // setiap tap ditolak "HP ini belum terdaftar" tanpa jalan keluar.
        const dikenalServer =
            statusPerangkat !== null && perangkatUuidTersimpan === uuid;

        if (dikenalServer || uuidTerkirim === uuid) {
            return;
        }

        uuidTerkirim = uuid;

        router.post(
            daftarkanPerangkat.url(),
            { device_uuid: uuid },
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
            <p class="text-xs text-muted-foreground">
                Absen masuk {jadwal.buka_masuk}–{jadwal.tutup_masuk} · absen pulang
                buka {jadwal.buka_pulang}
            </p>
        {:else}
            <p class="text-sm text-muted-foreground">
                Hari ini bukan hari kerja.
            </p>
        {/if}

        {#if lokasis.length > 0}
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <MapPin class="size-4" aria-hidden="true" />
                Absen hanya di sekitar {lokasis
                    .map((lokasi) => lokasi.nama)
                    .join(', ')}
            </p>
        {/if}
    </section>

    <section
        class="g-tile gap-2 {sudahMasuk ? 'g-tone-green' : 'g-tone-yellow'}"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base">Status hari ini</h3>
            <div class="flex flex-wrap items-center gap-2">
                {#if hariIni}
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
                {:else}
                    <Badge variant="outline">Belum absen</Badge>
                {/if}
            </div>
        </div>

        {#if hariIni}
            <p class="text-sm">
                Masuk {hariIni.jam_masuk ?? '-'} · Pulang {hariIni.jam_pulang ??
                    '-'}
            </p>
        {/if}

        <div class="border-t border-foreground/10 pt-2">
            <JarakLokasi {lokasis} />
        </div>
    </section>

    {#if statusPerangkat === 'pending'}
        <section class="g-tile g-tone-yellow gap-2">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-4" aria-hidden="true" />
                <h3 class="text-base">HP ini menunggu persetujuan</h3>
            </div>
            <p class="text-sm">
                Permintaan ganti HP sudah masuk. Absen baru bisa dipakai setelah
                TU menyetujui HP ini.
            </p>
        </section>
    {:else if statusPerangkat === 'revoked'}
        <section class="g-tile g-tone-red gap-2">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-4" aria-hidden="true" />
                <h3 class="text-base">HP ini dicabut</h3>
            </div>
            <p class="text-sm">
                HP ini tidak lagi diizinkan untuk absen. Hubungi TU kalau ini
                memang HP kamu.
            </p>
        </section>
    {/if}

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
            disabled={deviceUuid === null || statusPerangkat !== 'active'}
        />
    {/if}

    {#if pengumumans.length > 0}
        <section class="g-tile g-tone-blue gap-3">
            <div class="flex items-center gap-2">
                <Megaphone class="size-4" aria-hidden="true" />
                <h3 class="text-base">Informasi</h3>
            </div>

            <ul class="grid gap-3">
                {#each pengumumans as pengumuman (pengumuman.id)}
                    <li class="grid gap-1">
                        <p class="text-sm font-bold">{pengumuman.judul}</p>
                        <p class="text-sm whitespace-pre-line">
                            {pengumuman.isi}
                        </p>
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
