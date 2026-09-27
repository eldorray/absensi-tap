<script module lang="ts">
    import { index } from '@/routes/pengumuman';

    export const layout = {
        breadcrumbs: [{ title: 'Pengumuman', href: index() }],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import AppHead from '@/components/AppHead.svelte';
    import PengumumanDetail from '@/components/PengumumanDetail.svelte';
    import PengumumanKartu from '@/components/PengumumanKartu.svelte';
    import { Badge } from '@/components/ui/badge';
    import { pembacaBaru, tandaiTerbaca } from '@/lib/pengumuman';
    import type { Pengumuman } from '@/lib/pengumuman';
    import { toUrl } from '@/lib/utils';

    let {
        pengumumans,
    }: {
        pengumumans: {
            data: Pengumuman[];
            current_page: number;
            last_page: number;
            total: number;
            prev_page_url: string | null;
            next_page_url: string | null;
        };
    } = $props();

    // Dibaca sekali saat halaman dibuka, supaya penanda "Baru" tidak hilang
    // selagi guru masih membacanya.
    const baru = pembacaBaru();
    const jumlahBaru = $derived(pengumumans.data.filter(baru).length);
    let detail = $state<PengumumanDetail | null>(null);

    $effect(() => {
        tandaiTerbaca(pengumumans.data);
    });
</script>

<AppHead title="Pengumuman" />

<div class="flex flex-col gap-4 px-4 py-5 safe-bottom">
    <section class="g-tile g-tone-plain">
        <div class="flex items-center gap-3">
            <div
                class="grid size-11 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary"
            >
                <Megaphone class="size-5" aria-hidden="true" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold">Pengumuman</h1>
                <p class="text-sm text-muted-foreground">
                    Informasi resmi dari sekolah
                </p>
            </div>
            {#if jumlahBaru > 0}
                <Badge>{jumlahBaru} baru</Badge>
            {/if}
        </div>
    </section>

    {#if pengumumans.data.length === 0}
        <section
            class="g-tile g-tone-plain items-center gap-3 py-10 text-center"
        >
            <div
                class="grid size-14 place-items-center rounded-3xl bg-muted text-muted-foreground"
            >
                <Megaphone class="size-6" aria-hidden="true" />
            </div>
            <div>
                <h2 class="font-bold">Belum ada pengumuman</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Informasi dari sekolah akan muncul di sini.
                </p>
            </div>
        </section>
    {:else}
        <ol class="grid grid-cols-2 gap-3" aria-label="Daftar pengumuman">
            {#each pengumumans.data as pengumuman, indeks (pengumuman.id)}
                <li class="grid">
                    <PengumumanKartu
                        {pengumuman}
                        baru={baru(pengumuman)}
                        onclick={() => detail?.buka(indeks)}
                    />
                </li>
            {/each}
        </ol>
        {#if pengumumans.last_page > 1}
            <nav
                class="flex items-center justify-between gap-2"
                aria-label="Halaman pengumuman"
            >
                {#if pengumumans.prev_page_url}
                    <Link
                        href={toUrl(pengumumans.prev_page_url)}
                        class="inline-flex min-h-11 items-center rounded-2xl px-4 text-sm font-semibold text-primary hover:bg-primary/10"
                        >Lebih baru</Link
                    >
                {:else}
                    <span></span>
                {/if}
                <span class="text-xs text-muted-foreground"
                    >Halaman {pengumumans.current_page} dari {pengumumans.last_page}</span
                >
                {#if pengumumans.next_page_url}
                    <Link
                        href={toUrl(pengumumans.next_page_url)}
                        class="inline-flex min-h-11 items-center rounded-2xl px-4 text-sm font-semibold text-primary hover:bg-primary/10"
                        >Lebih lama</Link
                    >
                {:else}
                    <span></span>
                {/if}
            </nav>
        {/if}
    {/if}
</div>

<PengumumanDetail bind:this={detail} pengumumans={pengumumans.data} {baru} />
