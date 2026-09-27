<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Rekap', href: '/admin/rekap' }],
    };
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import Printer from 'lucide-svelte/icons/printer';
    import {
        cetak,
        exportMethod,
        index,
    } from '@/actions/App/Http/Controllers/Admin/RekapController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';

    type Hari = {
        tanggal: string;
        status: string;
        label: string;
        anomali: string[];
    };
    type Baris = {
        user_id: number;
        nama: string;
        nip: string | null;
        hari: Hari[];
        ringkasan: Record<string, number>;
        hari_efektif: number;
        terlambat: number;
        menit_terlambat: number;
        persentase: number;
    };
    type Mode = 'bulanan' | 'periode';
    let {
        filter,
        gurus,
        rekap,
    }: {
        filter: {
            mode: Mode;
            tahun: number;
            bulan: number;
            mulai: string;
            selesai: string;
            user_id: number | null;
        };
        gurus: { id: number; name: string }[];
        rekap: { tanggals: string[]; baris: Baris[] };
    } = $props();
    let mode = $state<Mode>(filter.mode);
    let tahun = $state(filter.tahun);
    let bulan = $state(filter.bulan);
    let mulai = $state(filter.mulai);
    let selesai = $state(filter.selesai);
    let guruId = $state<number | null>(filter.user_id);
    const namaBulan = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];
    const warna: Record<string, string> = {
        hadir: 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]',
        terlambat: 'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink)]',
        alfa: 'bg-[var(--g-red-c)] text-[var(--g-red-ink)]',
        izin: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        sakit: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        cuti: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
    };
    const formatTanggal = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    /** Judul mengikuti filter yang sudah diterapkan, bukan yang sedang diketik. */
    let judul = $derived(
        filter.mode === 'periode'
            ? `${formatTanggal.format(new Date(filter.mulai))} – ${formatTanggal.format(new Date(filter.selesai))}`
            : `${namaBulan[filter.bulan - 1]} ${filter.tahun}`,
    );

    function query(): Record<string, string> {
        const params: Record<string, string> =
            mode === 'periode'
                ? { mode, mulai, selesai }
                : { mode, tahun: String(tahun), bulan: String(bulan) };

        if (guruId) {
            params.user_id = String(guruId);
        }

        return params;
    }
    function terapkan(): void {
        router.get(index.url(), query(), {
            preserveState: true,
            preserveScroll: true,
        });
    }
    function unduh(): void {
        window.location.href = exportMethod.url({ query: query() });
    }
    /** Laporan siap cetak dibuka di tab baru supaya filternya tidak hilang. */
    function cetakLaporan(): void {
        window.open(cetak.url({ query: query() }), '_blank', 'noopener');
    }

    function judulAnomali(hari: Hari): string {
        return hari.anomali.length > 0
            ? `${hari.label} · ${hari.anomali.join(', ')}`
            : hari.label;
    }
</script>

<AppHead title="Rekap absensi" />
<div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <h3>Rekap {judul}</h3>
        <div class="flex flex-wrap items-end gap-2">
            <select
                bind:value={mode}
                class="h-10 rounded-md border border-input bg-background px-3"
                aria-label="Jenis rekap"
            >
                <option value="bulanan">Bulanan</option>
                <option value="periode">Periode</option>
            </select>
            {#if mode === 'periode'}
                <input
                    type="date"
                    bind:value={mulai}
                    max={selesai}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Tanggal mulai"
                />
                <span class="self-center text-muted-foreground">s.d.</span>
                <input
                    type="date"
                    bind:value={selesai}
                    min={mulai}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Tanggal selesai"
                />
            {:else}
                <select
                    bind:value={bulan}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Bulan"
                >
                    {#each namaBulan as nama, index (nama)}<option
                            value={index + 1}>{nama}</option
                        >{/each}
                </select>
                <input
                    type="number"
                    bind:value={tahun}
                    min="2020"
                    max="2100"
                    class="h-10 w-24 rounded-md border border-input bg-background px-3"
                    aria-label="Tahun"
                />
            {/if}
            <select
                bind:value={guruId}
                class="h-10 rounded-md border border-input bg-background px-3"
                aria-label="Guru"
            >
                <option value={null}>Semua guru</option>
                {#each gurus as guru (guru.id)}<option value={guru.id}
                        >{guru.name}</option
                    >{/each}
            </select>
            <Button onclick={terapkan}>Terapkan</Button>
            <Button variant="outline" onclick={unduh}
                ><Download class="size-4" aria-hidden="true" /> CSV</Button
            >
            <Button variant="outline" onclick={cetakLaporan}>
                <Printer class="size-4" aria-hidden="true" />
                Laporan PDF
            </Button>
        </div>
        {#each Object.values(page.props.errors) as error, i (i)}<p
                class="text-sm text-destructive"
                role="alert"
            >
                {error}
            </p>{/each}
    </section>
    {#if filter.mode === 'periode'}
        <section class="g-tile g-tone-plain">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-muted-foreground">
                            <th class="px-2 py-2 text-left font-medium">Guru</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >Hari efektif</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >Hadir</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >Terlambat</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >Menit terlambat</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >Izin/Sakit/Cuti</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >Alfa</th
                            >
                            <th class="px-2 py-2 text-right font-medium"
                                >% Kehadiran</th
                            >
                        </tr>
                    </thead>
                    <tbody>
                        {#each rekap.baris as baris (baris.user_id)}
                            <tr class="border-t border-border">
                                <th class="px-2 py-2 text-left font-medium"
                                    >{baris.nama}{#if baris.nip}<span
                                            class="block text-xs font-normal text-muted-foreground"
                                            >{baris.nip}</span
                                        >{/if}</th
                                >
                                <td class="px-2 py-2 text-right tabular-nums"
                                    >{baris.hari_efektif}</td
                                >
                                <td class="px-2 py-2 text-right tabular-nums"
                                    >{baris.ringkasan.hadir ?? 0}</td
                                >
                                <td class="px-2 py-2 text-right tabular-nums"
                                    >{baris.terlambat}</td
                                >
                                <td class="px-2 py-2 text-right tabular-nums"
                                    >{baris.menit_terlambat}</td
                                >
                                <td class="px-2 py-2 text-right tabular-nums"
                                    >{(baris.ringkasan.izin ?? 0) +
                                        (baris.ringkasan.sakit ?? 0) +
                                        (baris.ringkasan.cuti ?? 0)}</td
                                >
                                <td class="px-2 py-2 text-right tabular-nums"
                                    >{baris.ringkasan.alfa ?? 0}</td
                                >
                                <td
                                    class="px-2 py-2 text-right font-semibold tabular-nums"
                                    >{baris.persentase.toLocaleString(
                                        'id-ID',
                                    )}%</td
                                >
                            </tr>
                        {:else}
                            <tr
                                ><td
                                    colspan="8"
                                    class="px-2 py-4 text-center text-muted-foreground"
                                    >Belum ada akun guru.</td
                                ></tr
                            >
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    {:else}
        <section class="g-tile g-tone-plain">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead
                        ><tr
                            ><th
                                class="sticky left-0 bg-card px-2 py-2 text-left"
                                >Guru</th
                            >
                            {#each rekap.tanggals as tanggal (tanggal)}<th
                                    class="px-1 py-2 text-center font-medium text-muted-foreground"
                                    >{Number(tanggal.slice(-2))}</th
                                >{/each}
                        </tr></thead
                    >
                    <tbody
                        >{#each rekap.baris as baris (baris.user_id)}
                            <tr class="border-t border-border"
                                ><th
                                    class="sticky left-0 bg-card px-2 py-2 text-left font-medium"
                                    >{baris.nama}{#if baris.nip}<span
                                            class="block text-xs text-muted-foreground"
                                            >{baris.nip}</span
                                        >{/if}</th
                                >
                                {#each baris.hari as hari (hari.tanggal)}<td
                                        class="px-1 py-1 text-center"
                                        ><span
                                            class="inline-flex size-7 items-center justify-center rounded-lg text-xs font-semibold {warna[
                                                hari.status
                                            ] ?? 'text-muted-foreground'}"
                                            title={judulAnomali(hari)}
                                            >{hari.label.slice(
                                                0,
                                                1,
                                            )}{#if hari.anomali.includes('koordinat_kembar')}<span
                                                    class="sr-only"
                                                    >koordinat kembar</span
                                                >!{/if}</span
                                        ></td
                                    >{/each}
                            </tr>
                        {/each}</tbody
                    >
                </table>
            </div>
        </section>
        <section class="g-tile g-tone-plain">
            <h3>Ringkasan</h3>
            <ul class="divide-y divide-border">
                {#each rekap.baris as baris (baris.user_id)}<li
                        class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                    >
                        <span class="font-medium">{baris.nama}</span><span
                            class="text-muted-foreground"
                            >Hadir {baris.ringkasan.hadir ?? 0} · Terlambat {baris
                                .ringkasan.terlambat ?? 0} · Alfa {baris
                                .ringkasan.alfa ?? 0} · Izin {(baris.ringkasan
                                .izin ?? 0) +
                                (baris.ringkasan.sakit ?? 0) +
                                (baris.ringkasan.cuti ?? 0)}</span
                        >
                    </li>{/each}
            </ul>
        </section>
    {/if}
</div>
