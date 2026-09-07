<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Rekap', href: '/admin/rekap' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
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
    };
    let {
        filter,
        gurus,
        rekap,
    }: {
        filter: { tahun: number; bulan: number; user_id: number | null };
        gurus: { id: number; name: string }[];
        rekap: { tanggals: string[]; baris: Baris[] };
    } = $props();
    let tahun = $state(filter.tahun);
    let bulan = $state(filter.bulan);
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
    function terapkan(): void {
        router.get(
            index.url(),
            { tahun, bulan, user_id: guruId ?? undefined },
            { preserveState: true, preserveScroll: true },
        );
    }
    function unduh(): void {
        const params = new URLSearchParams({
            tahun: String(tahun),
            bulan: String(bulan),
        });

        if (guruId) {
            params.set('user_id', String(guruId));
        }

        window.location.href = exportMethod.url({
            query: Object.fromEntries(params),
        });
    }
    /** Laporan siap cetak dibuka di tab baru supaya filternya tidak hilang. */
    function cetakLaporan(): void {
        const params = new URLSearchParams({
            tahun: String(tahun),
            bulan: String(bulan),
        });

        if (guruId) {
            params.set('user_id', String(guruId));
        }

        window.open(
            cetak.url({ query: Object.fromEntries(params) }),
            '_blank',
            'noopener',
        );
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
        <h3>Rekap {namaBulan[bulan - 1]} {tahun}</h3>
        <div class="flex flex-wrap items-end gap-2">
            <select
                bind:value={bulan}
                class="h-10 rounded-md border border-input bg-background px-3"
                aria-label="Bulan"
            >
                {#each namaBulan as nama, index (nama)}<option value={index + 1}
                        >{nama}</option
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
    </section>
    <section class="g-tile g-tone-plain">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead
                    ><tr
                        ><th class="sticky left-0 bg-card px-2 py-2 text-left"
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
                            .ringkasan.terlambat ?? 0} · Alfa {baris.ringkasan
                            .alfa ?? 0} · Izin {(baris.ringkasan.izin ?? 0) +
                            (baris.ringkasan.sakit ?? 0) +
                            (baris.ringkasan.cuti ?? 0)}</span
                    >
                </li>{/each}
        </ul>
    </section>
</div>
