<script module lang="ts">
    import { index } from '@/routes/absensi-siswa';
    export const layout = {
        title: 'Absensi Siswa',
        breadcrumbs: [{ title: 'Absensi Siswa', href: index() }],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { show } from '@/routes/absensi-siswa';
    type Kelas = {
        id: number;
        nama: string;
        kantor: string | null;
        jumlah_siswa: number;
        status: string;
        terakhir_disimpan: string | null;
    };
    let { kelas, tanggal }: { kelas: Kelas[]; tanggal: string } = $props();
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
        <p>{tanggal} · Pilih kelas yang akan diperiksa.</p>
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
                href={toUrl(show(item.id))}
                class="flex min-h-12 items-center justify-center rounded-2xl bg-primary px-4 font-bold text-primary-foreground"
                >{item.status === 'final' || item.status === 'dikoreksi'
                    ? 'Lihat hasil'
                    : 'Periksa absensi'}</Link
            >
        </section>
    {:else}<section class="g-tile g-tone-plain text-center">
            <h2 class="font-bold">Tidak ada kelas</h2>
            <p class="text-muted-foreground">
                Anda tidak mempunyai kelas aktif untuk diabsen hari ini.
            </p>
        </section>{/each}
</div>
