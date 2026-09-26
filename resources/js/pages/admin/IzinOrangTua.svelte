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
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
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
        <section class="grid gap-3">
            {#each izins as izin (izin.id)}
                <article class="g-tile g-tone-plain gap-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-black">{izin.siswa}</h2>
                                <Badge variant="outline">NIS {izin.nis}</Badge>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Diajukan oleh {izin.orang_tua} · {izin.email_orang_tua}
                            </p>
                        </div>
                        <Badge
                            variant={izin.status === 'ditolak'
                                ? 'destructive'
                                : izin.status === 'disetujui'
                                  ? 'default'
                                  : 'secondary'}
                        >
                            {labelStatus[izin.status] ?? izin.status}
                        </Badge>
                    </div>

                    <div
                        class="grid gap-3 rounded-2xl bg-muted/55 p-4 sm:grid-cols-[10rem_1fr]"
                    >
                        <div>
                            <p
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Jenis dan tanggal
                            </p>
                            <p class="mt-1 font-bold capitalize">{izin.tipe}</p>
                            <p class="text-sm text-muted-foreground">
                                {izin.tanggal_mulai} – {izin.tanggal_selesai}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Alasan
                            </p>
                            <p class="mt-1 text-sm">{izin.alasan}</p>
                        </div>
                    </div>

                    {#if izin.ada_lampiran}
                        <a
                            href={lampiran.url(izin.id)}
                            class="inline-flex min-h-10 w-fit items-center gap-2 font-semibold text-primary"
                        >
                            <Paperclip class="size-4" aria-hidden="true" />
                            Unduh lampiran orang tua
                        </a>
                    {/if}

                    {#if izin.status === 'pending'}
                        <div class="grid gap-3 border-t border-border/70 pt-4">
                            <label
                                for={`catatan-${izin.id}`}
                                class="text-sm font-medium"
                            >
                                Catatan admin
                                <span class="text-muted-foreground"
                                    >(opsional)</span
                                >
                            </label>
                            <textarea
                                id={`catatan-${izin.id}`}
                                rows="2"
                                maxlength="1000"
                                placeholder="Tambahkan penjelasan untuk orang tua"
                                bind:value={catatan[izin.id]}
                                class="resize-none rounded-2xl border border-input bg-background px-4 py-3 text-base"
                            ></textarea>
                            <div
                                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                            >
                                <Button
                                    type="button"
                                    variant="destructive"
                                    class="min-h-11 rounded-2xl"
                                    disabled={diproses === izin.id}
                                    onclick={() => review(izin.id, 'ditolak')}
                                >
                                    <X class="size-4" aria-hidden="true" />
                                    Tolak
                                </Button>
                                <Button
                                    type="button"
                                    class="min-h-11 rounded-2xl"
                                    disabled={diproses === izin.id}
                                    onclick={() => review(izin.id, 'disetujui')}
                                >
                                    <Check class="size-4" aria-hidden="true" />
                                    Setujui
                                </Button>
                            </div>
                        </div>
                    {:else if izin.catatan_review}
                        <p class="rounded-2xl bg-muted px-4 py-3 text-sm">
                            Catatan admin: {izin.catatan_review}
                        </p>
                    {/if}
                </article>
            {/each}
        </section>
    {/if}
</div>
