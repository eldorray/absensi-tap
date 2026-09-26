<script module lang="ts">
    import { index as izinOrangTuaIndex } from '@/routes/admin/izin-orang-tua';

    export const layout = {
        breadcrumbs: [{ title: 'Izin orang tua', href: izinOrangTuaIndex() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import FileHeart from 'lucide-svelte/icons/file-heart';
    import Paperclip from 'lucide-svelte/icons/paperclip';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { lampiran, update } from '@/routes/admin/izin-orang-tua';

    type IzinBaris = {
        id: number;
        orang_tua: string;
        email_orang_tua: string;
        siswa: string;
        nis: string;
        tipe: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        status: string;
        catatan_review: string | null;
        ada_lampiran: boolean;
    };

    let { izins }: { izins: IzinBaris[] } = $props();

    let catatan = $state<Record<number, string>>({});
    let diproses = $state<number | null>(null);

    const labelStatus: Record<string, string> = {
        pending: 'Menunggu review',
        disetujui: 'Disetujui',
        ditolak: 'Ditolak',
    };

    function review(id: number, status: 'disetujui' | 'ditolak'): void {
        diproses = id;
        router.patch(
            update.url(id),
            { status, catatan_review: catatan[id] ?? null },
            {
                preserveScroll: true,
                onFinish: () => (diproses = null),
            },
        );
    }
</script>

<AppHead title="Izin Orang Tua" />

<div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-green overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <span
                    class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground"
                >
                    <FileHeart class="size-6" aria-hidden="true" />
                </span>
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.12em] text-primary uppercase"
                    >
                        Kesiswaan
                    </p>
                    <h1 class="mt-1 text-xl font-black">
                        Pengajuan izin orang tua
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Review pemberitahuan izin atau sakit siswa. Menu ini
                        terpisah dari izin guru.
                    </p>
                </div>
            </div>
            <Badge variant="secondary">
                {izins.filter((izin) => izin.status === 'pending').length} menunggu
            </Badge>
        </div>
    </section>

    {#if izins.length === 0}
        <section class="g-tile g-tone-plain py-12 text-center">
            <FileHeart
                class="mx-auto mb-3 size-9 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 class="font-bold">Belum ada pengajuan</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Pengajuan dari akun orang tua akan muncul di sini.
            </p>
        </section>
    {:else}
        <section class="g-tile g-tone-plain gap-4">
            <div class="overflow-x-auto rounded-2xl border border-border">
                <table
                    class="w-full min-w-[55rem] table-fixed text-left text-sm"
                >
                    <colgroup>
                        <col class="w-[13%]" />
                        <col class="w-[16%]" />
                        <col class="w-[17%]" />
                        <col class="w-[19%]" />
                        <col class="w-[12%]" />
                        <col class="w-[23%]" />
                    </colgroup>
                    <thead class="bg-muted/80 text-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Siswa</th>
                            <th class="px-4 py-3 font-medium">Orang tua</th>
                            <th class="px-4 py-3 font-medium">Pengajuan</th>
                            <th class="px-4 py-3 font-medium">Alasan</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each izins as izin (izin.id)}
                            <tr class="border-t border-border align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{izin.siswa}</p>
                                    <p class="text-xs text-muted-foreground">
                                        NIS {izin.nis}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold">
                                        {izin.orang_tua}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {izin.email_orang_tua}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold capitalize">
                                        {izin.tipe}
                                    </p>
                                    <p
                                        class="whitespace-nowrap text-xs text-muted-foreground"
                                    >
                                        {izin.tanggal_mulai} – {izin.tanggal_selesai}
                                    </p>
                                    {#if izin.ada_lampiran}
                                        <a
                                            href={lampiran.url(izin.id)}
                                            class="mt-1 inline-flex min-h-8 items-center gap-1.5 font-semibold text-primary underline-offset-4 hover:underline"
                                        >
                                            <Paperclip
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            Unduh lampiran
                                        </a>
                                    {/if}
                                </td>
                                <td class="px-4 py-3">
                                    <p class="leading-relaxed">{izin.alasan}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant={izin.status === 'ditolak'
                                            ? 'destructive'
                                            : izin.status === 'disetujui'
                                              ? 'default'
                                              : 'secondary'}
                                    >
                                        {labelStatus[izin.status] ??
                                            izin.status}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    {#if izin.status === 'pending'}
                                        <div class="grid gap-2">
                                            <input
                                                type="text"
                                                maxlength="1000"
                                                aria-label={`Catatan review izin ${izin.siswa}`}
                                                placeholder="Catatan (opsional)"
                                                bind:value={catatan[izin.id]}
                                                class="h-10 w-full rounded-xl border border-input bg-background px-3 text-base"
                                            />
                                            <div class="flex justify-end gap-1">
                                                <TombolIkon
                                                    ikon={Check}
                                                    nada="hijau"
                                                    disabled={diproses ===
                                                        izin.id}
                                                    label={`Setujui izin ${izin.siswa}`}
                                                    onclick={() =>
                                                        review(
                                                            izin.id,
                                                            'disetujui',
                                                        )}
                                                />
                                                <TombolIkon
                                                    ikon={X}
                                                    nada="merah"
                                                    disabled={diproses ===
                                                        izin.id}
                                                    label={`Tolak izin ${izin.siswa}`}
                                                    onclick={() =>
                                                        review(
                                                            izin.id,
                                                            'ditolak',
                                                        )}
                                                />
                                            </div>
                                        </div>
                                    {:else}
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {izin.catatan_review
                                                ? `Catatan: ${izin.catatan_review}`
                                                : 'Sudah direview tanpa catatan.'}
                                        </p>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </section>
    {/if}
</div>
