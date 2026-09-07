<script module lang="ts">
    import { dashboard } from '@/routes/admin';
    export const layout = {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { index as guruIndex } from '@/routes/admin/guru';
    import { index as izinIndex } from '@/routes/admin/izin';
    import { index as rekapHarianIndex } from '@/routes/admin/rekap-harian';
    import { index as userIndex } from '@/routes/admin/user';

    type Log = {
        id: number;
        nama: string;
        tipe: string;
        hasil: string;
        diterima: boolean;
        waktu: string | null;
        lokasi: string | null;
        jarak_meter: number | null;
        terverifikasi: boolean;
    };

    let {
        tanggal,
        ringkasanHariIni,
        perluTindakan,
        master,
        bulanIni,
        log,
    }: {
        tanggal: string;
        ringkasanHariIni: Record<string, number>;
        perluTindakan: Record<string, number>;
        master: Record<string, number>;
        bulanIni: Record<string, number>;
        log: Log[];
    } = $props();

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const namaBulan = new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric',
    });

    const labelStatus: Record<string, string> = {
        hadir: 'Hadir',
        terlambat: 'Terlambat',
        belum: 'Belum absen',
        alfa: 'Alfa',
        izin: 'Izin',
        sakit: 'Sakit',
        cuti: 'Cuti',
        libur: 'Libur',
        bukan_hari_kerja: 'Bukan hari kerja',
    };

    const nada: Record<string, string> = {
        hadir: 'g-tone-green',
        terlambat: 'g-tone-yellow',
        alfa: 'g-tone-red',
        belum: 'g-tone-yellow',
        izin: 'g-tone-blue',
        sakit: 'g-tone-blue',
        cuti: 'g-tone-blue',
    };

    const labelHasil: Record<string, string> = {
        diterima: 'Diterima',
        luar_radius: 'Luar radius',
        akurasi_buruk: 'GPS lemah',
        perangkat_asing: 'HP asing',
        duplikat: 'Duplikat',
        passkey_invalid: 'Biometrik gagal',
        belum_masuk: 'Belum tap masuk',
        luar_jadwal: 'Luar jendela',
    };

    /** Hanya status yang benar-benar ada hari ini, terbanyak dulu. */
    const statusHariIni = $derived(
        Object.entries(ringkasanHariIni).sort((a, b) => b[1] - a[1]),
    );

    const tindakan = $derived(
        [
            {
                kunci: 'izin_menunggu',
                label: 'Izin menunggu persetujuan',
                href: izinIndex(),
            },
            {
                kunci: 'perangkat_menunggu',
                label: 'HP menunggu persetujuan',
                href: guruIndex(),
            },
            {
                kunci: 'guru_tanpa_kantor',
                label: 'Guru belum punya kantor',
                href: userIndex(),
            },
            {
                kunci: 'guru_nonaktif',
                label: 'Guru nonaktif',
                href: userIndex(),
            },
        ].filter((t) => (perluTindakan[t.kunci] ?? 0) > 0),
    );
</script>

<AppHead title="Dashboard" />

<div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3>Hari ini</h3>
                <p class="text-muted-foreground">
                    {tanggalPanjang.format(new Date(`${tanggal}T00:00`))}
                </p>
            </div>
            <Button
                variant="outline"
                onclick={() => router.visit(toUrl(rekapHarianIndex()))}
            >
                Rekap harian
                <ArrowRight class="size-4" aria-hidden="true" />
            </Button>
        </div>

        {#if statusHariIni.length === 0}
            <p class="text-muted-foreground">
                Belum ada akun guru, jadi belum ada yang bisa direkap.
            </p>
        {:else}
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                {#each statusHariIni as [status, jumlah] (status)}
                    <div
                        class="g-tile gap-1 px-4 py-3 {nada[status] ??
                            'g-tone-plain'}"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide uppercase"
                        >
                            {labelStatus[status] ?? status}
                        </p>
                        <p class="font-mono text-3xl font-bold tabular-nums">
                            {jumlah}
                        </p>
                    </div>
                {/each}
            </div>
        {/if}
    </section>

    {#if tindakan.length > 0}
        <section class="g-tile g-tone-yellow gap-3">
            <h3 class="text-base">Perlu tindakan</h3>
            <ul class="grid gap-2">
                {#each tindakan as t (t.kunci)}
                    <li
                        class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-background/60 px-4 py-2"
                    >
                        <span class="text-sm"
                            >{t.label}: <b>{perluTindakan[t.kunci]}</b></span
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            onclick={() => router.visit(toUrl(t.href))}
                            >Buka</Button
                        >
                    </li>
                {/each}
            </ul>
        </section>
    {/if}

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="g-tile g-tone-plain gap-3">
            <h3 class="text-base">
                Bulan ini · {namaBulan.format(new Date(`${tanggal}T00:00`))}
            </h3>
            <dl class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Absen hadir</dt>
                    <dd class="font-mono font-bold">{bulanIni.hadir}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Terlambat</dt>
                    <dd class="font-mono font-bold">{bulanIni.terlambat}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Pulang cepat</dt>
                    <dd class="font-mono font-bold">{bulanIni.pulang_cepat}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">
                        Belum tap pulang
                    </dt>
                    <dd class="font-mono font-bold">
                        {bulanIni.belum_tap_pulang}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="g-tile g-tone-plain gap-3">
            <h3 class="text-base">Data master</h3>
            <dl class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Guru</dt>
                    <dd class="font-mono font-bold">{master.guru}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Admin</dt>
                    <dd class="font-mono font-bold">{master.admin}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Kantor</dt>
                    <dd class="font-mono font-bold">{master.kantor}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Lokasi aktif</dt>
                    <dd class="font-mono font-bold">{master.lokasi_aktif}</dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="g-tile g-tone-plain gap-3">
        <div>
            <h3 class="text-base">Log tap terbaru</h3>
            <p class="text-muted-foreground">
                Termasuk yang ditolak — HP asing, di luar radius, dan biometrik
                gagal adalah jejak percobaan titip absen.
            </p>
        </div>

        {#if log.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Belum ada tap tercatat.
            </p>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="px-2 py-2 font-medium">Waktu</th>
                            <th class="px-2 py-2 font-medium">Guru</th>
                            <th class="px-2 py-2 font-medium">Tap</th>
                            <th class="px-2 py-2 font-medium">Hasil</th>
                            <th class="px-2 py-2 font-medium">Lokasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each log as l (l.id)}
                            <tr class="border-t border-border/60">
                                <td
                                    class="px-2 py-2 font-mono whitespace-nowrap"
                                    >{l.waktu ?? '-'}</td
                                >
                                <td class="px-2 py-2">{l.nama}</td>
                                <td class="px-2 py-2 capitalize">{l.tipe}</td>
                                <td class="px-2 py-2">
                                    <div
                                        class="flex flex-wrap items-center gap-1"
                                    >
                                        <Badge
                                            variant={l.diterima
                                                ? 'secondary'
                                                : 'destructive'}
                                            >{labelHasil[l.hasil] ??
                                                l.hasil}</Badge
                                        >
                                        {#if l.diterima && !l.terverifikasi}
                                            <Badge
                                                variant="outline"
                                                class="gap-1"
                                            >
                                                <ShieldAlert
                                                    class="size-3"
                                                    aria-hidden="true"
                                                />
                                                Tanpa biometrik
                                            </Badge>
                                        {/if}
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-muted-foreground">
                                    {l.lokasi ?? '-'}{l.jarak_meter !== null
                                        ? ` · ${l.jarak_meter} m`
                                        : ''}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>
</div>
