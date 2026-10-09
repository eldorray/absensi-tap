<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Izin guru', href: '/admin/izin' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Paperclip from 'lucide-svelte/icons/paperclip';
    import X from 'lucide-svelte/icons/x';
    import { update } from '@/actions/App/Http/Controllers/Admin/IzinController';
    import { lampiran } from '@/actions/App/Http/Controllers/IzinController';
    import AppHead from '@/components/AppHead.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';

    type IzinBaris = {
        id: number;
        guru: string;
        nip: string | null;
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

    const formatTanggal = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    // formatRange meringkas sendiri: "9 Okt 2026" atau "9–12 Okt 2026".
    function rentang(izin: IzinBaris): string {
        return formatTanggal.formatRange(
            new Date(`${izin.tanggal_mulai}T00:00`),
            new Date(`${izin.tanggal_selesai}T00:00`),
        );
    }

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

<AppHead title="Izin guru" />

<div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-4">
        <div>
            <h1 class="text-xl font-black">Pengajuan izin guru</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Periksa pengajuan izin, sakit, atau cuti dari guru.
            </p>
        </div>

        {#if izins.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-8 text-center text-muted-foreground"
            >
                Belum ada pengajuan.
            </p>
        {:else}
            <div class="overflow-x-auto rounded-2xl border border-border">
                <table
                    class="w-full min-w-[36rem] table-fixed text-left text-sm"
                >
                    <!-- Kolom Status berlebar tetap agar badge tidak terpotong;
                         kontrol review di sub-baris supaya tabel tidak melebar. -->
                    <colgroup>
                        <col class="w-[26%]" />
                        <col class="w-[20%]" />
                        <col />
                        <col class="w-40" />
                    </colgroup>
                    <thead class="bg-muted/80 text-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Guru</th>
                            <th class="px-4 py-3 font-medium">Pengajuan</th>
                            <th class="px-4 py-3 font-medium">Alasan</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each izins as izin (izin.id)}
                            <tr class="border-t border-border align-top">
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{izin.guru}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {izin.nip
                                            ? `NIP ${izin.nip}`
                                            : 'NIP belum diisi'}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold capitalize">
                                        {izin.tipe}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {rentang(izin)}
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
                                            Lampiran
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
                            </tr>
                            {#if izin.status === 'pending'}
                                <tr>
                                    <td colspan="4" class="px-4 pb-4">
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="text"
                                                maxlength="1000"
                                                aria-label={`Catatan review izin ${izin.guru}`}
                                                placeholder="Catatan untuk guru (opsional)"
                                                bind:value={catatan[izin.id]}
                                                class="mr-1 h-10 min-w-0 flex-1 rounded-xl border border-input bg-background px-3 text-base"
                                            />
                                            <TombolIkon
                                                ikon={Check}
                                                nada="hijau"
                                                disabled={diproses === izin.id}
                                                label={`Setujui izin ${izin.guru}`}
                                                onclick={() =>
                                                    review(
                                                        izin.id,
                                                        'disetujui',
                                                    )}
                                            />
                                            <TombolIkon
                                                ikon={X}
                                                nada="merah"
                                                disabled={diproses === izin.id}
                                                label={`Tolak izin ${izin.guru}`}
                                                onclick={() =>
                                                    review(izin.id, 'ditolak')}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            {:else if izin.catatan_review}
                                <tr>
                                    <td
                                        colspan="4"
                                        class="px-4 pb-3 text-sm text-muted-foreground"
                                    >
                                        Catatan: {izin.catatan_review}
                                    </td>
                                </tr>
                            {/if}
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>
</div>
