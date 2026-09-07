<script module lang="ts">
    import { index } from '@/routes/admin/rekap-harian';
    export const layout = {
        breadcrumbs: [{ title: 'Rekap harian', href: index() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Search from 'lucide-svelte/icons/search';
    import {
        exportMethod,
        index as rekapHarianIndex,
        reset as rekapHarianReset,
    } from '@/actions/App/Http/Controllers/Admin/RekapHarianController';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Baris = {
        user_id: number;
        nama: string;
        nip: string | null;
        status: string;
        label: string;
        jadwal: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        jarak_meter: number | null;
        lokasi: string | null;
        terverifikasi: boolean;
        anomali: string[];
    };

    let {
        rekap,
        labelAnomali,
    }: {
        rekap: {
            tanggal: string;
            ringkasan: Record<string, number>;
            baris: Baris[];
        };
        labelAnomali: Record<string, string>;
    } = $props();

    let cari = $state('');

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const warna: Record<string, string> = {
        hadir: 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]',
        terlambat: 'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink)]',
        alfa: 'bg-[var(--g-red-c)] text-[var(--g-red-ink)]',
        izin: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        sakit: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        cuti: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
    };

    /** Ringkasan hanya menampilkan status yang benar-benar ada hari itu. */
    const ringkasan = $derived(
        Object.entries(rekap.ringkasan).sort((a, b) => b[1] - a[1]),
    );

    const labelStatus: Record<string, string> = {
        hadir: 'Hadir',
        terlambat: 'Terlambat',
        alfa: 'Alfa',
        izin: 'Izin',
        sakit: 'Sakit',
        cuti: 'Cuti',
        belum: 'Belum absen',
        libur: 'Libur',
        bukan_hari_kerja: 'Bukan hari kerja',
    };

    const terfilter = $derived(
        rekap.baris.filter((b) =>
            `${b.nama} ${b.nip ?? ''}`
                .toLowerCase()
                .includes(cari.trim().toLowerCase()),
        ),
    );

    /**
     * Hapus absen satu guru pada tanggal yang sedang dilihat.
     *
     * Destruktif dan dipakai untuk membetulkan salah tap, jadi selalu lewat
     * konfirmasi yang menyebut nama dan tanggalnya.
     */
    let konfirmasi = $state<Konfirmasi | null>(null);

    function resetAbsen(baris: Baris): void {
        const tanggal = tanggalPanjang.format(
            new Date(`${rekap.tanggal}T00:00`),
        );

        konfirmasi = {
            judul: `Reset absen ${baris.nama}?`,
            pesan: `Absen pada ${tanggal} dihapus sehingga dia bisa absen ulang. Riwayat tap beserta jam dan koordinatnya tetap tersimpan.`,
            label: 'Reset absen',
            aksi: () =>
                router.delete(rekapHarianReset(baris.user_id).url, {
                    data: { tanggal: rekap.tanggal },
                    preserveScroll: true,
                }),
        };
    }

    function pilihTanggal(tanggal: string): void {
        router.get(
            rekapHarianIndex.url(),
            { tanggal },
            { preserveState: true, preserveScroll: true },
        );
    }
</script>

<AppHead title="Rekap harian" />

<div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3>Rekap harian</h3>
                <p class="text-muted-foreground">
                    {tanggalPanjang.format(new Date(`${rekap.tanggal}T00:00`))}
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div class="grid gap-1.5">
                    <Label for="tanggal" class="sr-only">Tanggal</Label>
                    <Input
                        id="tanggal"
                        type="date"
                        value={rekap.tanggal}
                        onchange={(e) => pilihTanggal(e.currentTarget.value)}
                    />
                </div>
                <Button
                    variant="outline"
                    onclick={() =>
                        (window.location.href = exportMethod.url({
                            query: { tanggal: rekap.tanggal },
                        }))}
                >
                    <Download class="size-4" aria-hidden="true" />
                    CSV
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            {#each ringkasan as [status, jumlah] (status)}
                <span
                    class="rounded-full px-3 py-1 text-sm font-semibold {warna[
                        status
                    ] ?? 'bg-muted text-muted-foreground'}"
                >
                    {labelStatus[status] ?? status}: {jumlah}
                </span>
            {/each}
        </div>

        <div class="relative">
            <Label for="cari-guru" class="sr-only">Cari guru</Label>
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                id="cari-guru"
                class="pl-11"
                placeholder="Cari nama atau NIP"
                bind:value={cari}
            />
        </div>

        {#if terfilter.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                {rekap.baris.length === 0
                    ? 'Belum ada akun guru.'
                    : `Tidak ada guru cocok dengan "${cari}".`}
            </p>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="px-2 py-2 font-medium">Guru</th>
                            <th class="px-2 py-2 font-medium">Jadwal</th>
                            <th class="px-2 py-2 font-medium">Masuk</th>
                            <th class="px-2 py-2 font-medium">Pulang</th>
                            <th class="px-2 py-2 font-medium">Status</th>
                            <th class="px-2 py-2 font-medium">Catatan</th>
                            <th class="px-2 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each terfilter as b (b.user_id)}
                            <tr class="border-t border-border/60">
                                <td class="px-2 py-3">
                                    <p class="font-semibold">{b.nama}</p>
                                    {#if b.lokasi}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {b.lokasi}{b.jarak_meter !== null
                                                ? ` · ${b.jarak_meter} m`
                                                : ''}
                                        </p>
                                    {/if}
                                </td>
                                <td
                                    class="px-2 py-3 font-mono text-muted-foreground"
                                    >{b.jadwal ?? '-'}</td
                                >
                                <td class="px-2 py-3 font-mono"
                                    >{b.jam_masuk ?? '-'}</td
                                >
                                <td class="px-2 py-3 font-mono"
                                    >{b.jam_pulang ?? '-'}</td
                                >
                                <td class="px-2 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold {warna[
                                            b.status
                                        ] ?? 'bg-muted text-muted-foreground'}"
                                        >{b.label}</span
                                    >
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        {#each b.anomali as kode (kode)}
                                            <Badge variant="outline"
                                                >{labelAnomali[kode] ??
                                                    kode}</Badge
                                            >
                                        {/each}
                                    </div>
                                </td>
                                <td class="px-2 py-3 text-right">
                                    {#if b.jam_masuk !== null}
                                        <div class="flex justify-end">
                                            <TombolIkon
                                                ikon={RotateCcw}
                                                nada="kuning"
                                                label={`Reset absen ${b.nama}`}
                                                onclick={() => resetAbsen(b)}
                                            />
                                        </div>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-muted-foreground">
                Menampilkan {terfilter.length} dari {rekap.baris.length} guru.
            </p>
        {/if}
    </section>
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
