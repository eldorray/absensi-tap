<script module lang="ts">
    import { index } from '@/routes/absensi-siswa';
    export const layout = {
        title: 'Absensi Siswa',
        breadcrumbs: [{ title: 'Absensi Siswa', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import CalendarRange from 'lucide-svelte/icons/calendar-range';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import FileDown from 'lucide-svelte/icons/file-down';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { cetak, show } from '@/routes/absensi-siswa';
    type Kelas = {
        id: number;
        nama: string;
        kantor: string | null;
        jumlah_siswa: number;
        status: string;
        terakhir_disimpan: string | null;
    };
    let { kelas, tanggal }: { kelas: Kelas[]; tanggal: string } = $props();
    const hariIni = new Date().toLocaleDateString('sv-SE');
    const lampau = $derived(tanggal !== hariIni);
    function pindahTanggal(event: Event): void {
        const pilihan = (event.currentTarget as HTMLInputElement).value;

        if (pilihan) {
            router.get(
                index.url(),
                { tanggal: pilihan },
                { preserveScroll: true },
            );
        }
    }
    let periodeTerbuka = $state(false);
    let dari = $state(tanggal);
    let sampai = $state(tanggal);
    const urlHarian = $derived(
        cetak.url({ query: { dari: tanggal, sampai: tanggal } }),
    );
    const urlPeriode = $derived(cetak.url({ query: { dari, sampai } }));
    const periodeValid = $derived(
        dari !== '' && sampai !== '' && dari <= sampai,
    );
    const label: Record<string, string> = {
        belum_diperiksa: 'Belum diperiksa',
        draft: 'Draft',
        final: 'Final',
        dikoreksi: 'Dikoreksi',
    };
</script>

<AppHead title="Absensi Siswa" />
<div
    class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <section class="g-tile g-tone-green">
        <ClipboardCheck class="size-7" />
        <h1 class="g-display text-3xl">Absensi Siswa</h1>
        <p>
            {lampau
                ? 'Menengok hari lampau. Hasilnya hanya dapat dibaca.'
                : 'Pilih kelas yang akan diperiksa.'}
        </p>
        <label class="text-sm font-bold">
            Tanggal
            <input
                type="date"
                value={tanggal}
                max={hariIni}
                onchange={pindahTanggal}
                class="mt-1 min-h-12 w-full rounded-2xl border bg-background px-4 text-foreground"
            />
        </label>

        <div class="grid grid-cols-2 gap-2">
            <a
                href={urlHarian}
                target="_blank"
                rel="noopener"
                class="flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-current px-3 text-sm font-bold"
            >
                <FileDown class="size-4" /> PDF harian
            </a>
            <button
                type="button"
                aria-expanded={periodeTerbuka}
                onclick={() => (periodeTerbuka = !periodeTerbuka)}
                class="flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-current px-3 text-sm font-bold"
            >
                <CalendarRange class="size-4" /> PDF periode
            </button>
        </div>

        {#if periodeTerbuka}
            <div class="grid gap-2 rounded-2xl bg-background/20 p-3">
                <label class="text-xs font-bold">
                    Dari
                    <input
                        type="date"
                        bind:value={dari}
                        max={hariIni}
                        class="mt-1 min-h-11 w-full rounded-xl border bg-background px-3 text-foreground"
                    />
                </label>
                <label class="text-xs font-bold">
                    Sampai
                    <input
                        type="date"
                        bind:value={sampai}
                        max={hariIni}
                        class="mt-1 min-h-11 w-full rounded-xl border bg-background px-3 text-foreground"
                    />
                </label>
                {#if periodeValid}
                    <a
                        href={urlPeriode}
                        target="_blank"
                        rel="noopener"
                        class="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-3 text-sm font-bold text-primary-foreground"
                    >
                        <FileDown class="size-4" /> Buka PDF periode
                    </a>
                {:else}
                    <p class="text-xs font-semibold">
                        Tanggal "sampai" tidak boleh mendahului "dari".
                    </p>
                {/if}
            </div>
        {/if}
    </section>
    {#each kelas as item (item.id)}
        <section class="g-tile g-tone-plain">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold">{item.nama}</h2>
                    <p class="text-sm text-muted-foreground">
                        {item.kantor ?? 'Tanpa unit'} · {item.jumlah_siswa} siswa
                    </p>
                </div>
                <span
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"
                    >{label[item.status]}</span
                >
            </div>
            <p class="text-xs text-muted-foreground">
                {item.terakhir_disimpan
                    ? `Terakhir disimpan ${new Date(item.terakhir_disimpan).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`
                    : 'Belum pernah disimpan'}
            </p>
            <Link
                href={toUrl(show(item.id, { query: { tanggal } }))}
                class="flex min-h-12 items-center justify-center rounded-2xl bg-primary px-4 font-bold text-primary-foreground"
                >{lampau ||
                item.status === 'final' ||
                item.status === 'dikoreksi'
                    ? 'Lihat hasil'
                    : 'Periksa absensi'}</Link
            >
        </section>
    {:else}<section class="g-tile g-tone-plain text-center">
            <h2 class="font-bold">Tidak ada kelas</h2>
            <p class="text-muted-foreground">
                Tidak ada kelas yang Anda ampu pada tanggal ini.
            </p>
        </section>{/each}
</div>
