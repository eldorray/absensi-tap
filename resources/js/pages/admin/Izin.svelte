<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Izin masuk', href: '/admin/izin' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { update } from '@/actions/App/Http/Controllers/Admin/IzinController';
    import { lampiran } from '@/actions/App/Http/Controllers/IzinController';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';

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

    function review(id: number, status: 'disetujui' | 'ditolak'): void {
        router.patch(
            update.url(id),
            { status, catatan_review: catatan[id] ?? null },
            { preserveScroll: true },
        );
    }
</script>

<AppHead title="Izin masuk" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <h3>Pengajuan izin</h3>

        {#if izins.length === 0}
            <p>Belum ada pengajuan.</p>
        {:else}
            <ul class="divide-y divide-border">
                {#each izins as izin (izin.id)}
                    <li class="grid gap-2 py-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span class="font-medium">
                                {izin.guru}{izin.nip ? ` · ${izin.nip}` : ''}
                            </span>
                            <Badge
                                variant={izin.status === 'pending'
                                    ? 'secondary'
                                    : 'outline'}
                            >
                                {izin.status}
                            </Badge>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {izin.tipe} · {izin.tanggal_mulai} – {izin.tanggal_selesai}
                        </p>
                        <p class="text-sm">{izin.alasan}</p>

                        {#if izin.ada_lampiran}
                            <a
                                href={lampiran.url(izin.id)}
                                class="text-sm font-medium text-primary"
                            >
                                Unduh lampiran
                            </a>
                        {/if}

                        {#if izin.status === 'pending'}
                            <input
                                type="text"
                                placeholder="Catatan (opsional)"
                                bind:value={catatan[izin.id]}
                                class="h-10 rounded-md border border-input bg-background px-3 text-base"
                            />
                            <div class="flex gap-2">
                                <Button
                                    size="sm"
                                    onclick={() => review(izin.id, 'disetujui')}
                                >
                                    Setujui
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onclick={() => review(izin.id, 'ditolak')}
                                >
                                    Tolak
                                </Button>
                            </div>
                        {:else if izin.catatan_review}
                            <p class="text-sm text-muted-foreground">
                                Catatan: {izin.catatan_review}
                            </p>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
