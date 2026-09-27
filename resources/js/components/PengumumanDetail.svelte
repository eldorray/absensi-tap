<script lang="ts">
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import { tick } from 'svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
    } from '@/components/ui/sheet';
    import { labelWaktu, warnaSampul } from '@/lib/pengumuman';
    import type { Pengumuman } from '@/lib/pengumuman';

    let {
        pengumumans,
        baru,
    }: {
        pengumumans: Pengumuman[];
        baru: (pengumuman: Pengumuman) => boolean;
    } = $props();

    /*
     * Detail muncul dari bawah; di dalamnya pengumuman lain digeser ke
     * samping. Geseran memakai scroll-snap bawaan peramban, jadi terasa
     * seperti carousel native tanpa pustaka gestur.
     */
    let open = $state(false);
    let aktif = $state(0);
    let lintasan = $state<HTMLDivElement | null>(null);

    /** Dipanggil halaman lewat bind:this saat sebuah kartu diketuk. */
    export async function buka(indeks: number): Promise<void> {
        aktif = indeks;
        open = true;
        await tick();
        lintasan?.scrollTo({ left: indeks * lintasan.clientWidth });
    }

    function geserKe(indeks: number): void {
        if (lintasan === null) {
            return;
        }

        const tanpaGerak = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        lintasan.scrollTo({
            left: indeks * lintasan.clientWidth,
            behavior: tanpaGerak ? 'auto' : 'smooth',
        });
    }

    function saatDigeser(): void {
        if (lintasan === null || lintasan.clientWidth === 0) {
            return;
        }

        aktif = Math.round(lintasan.scrollLeft / lintasan.clientWidth);
    }

    function tombolPanah(event: KeyboardEvent): void {
        if (!open) {
            return;
        }

        if (event.key === 'ArrowRight' && aktif < pengumumans.length - 1) {
            geserKe(aktif + 1);
        } else if (event.key === 'ArrowLeft' && aktif > 0) {
            geserKe(aktif - 1);
        } else if (event.key === 'Escape') {
            open = false;
        }
    }
</script>

<svelte:window onkeydown={tombolPanah} />

<Sheet bind:open>
    <SheetContent
        side="bottom"
        class="inset-x-0 mx-auto h-fit max-h-[85svh] w-full max-w-lg gap-0 overflow-hidden rounded-t-[2rem] border-x border-t border-border/70 bg-background px-0 pt-0 pb-0 shadow-[0_-24px_80px_rgba(15,23,42,0.24)]"
    >
        <div class="shrink-0 px-5 pt-3">
            <div
                class="mx-auto h-1.5 w-11 rounded-full bg-muted-foreground/20"
            ></div>
            <SheetHeader class="mt-4 mb-0 pr-10 text-left">
                <SheetTitle class="text-sm font-semibold text-muted-foreground"
                    >Pengumuman {aktif + 1} dari {pengumumans.length}</SheetTitle
                >
            </SheetHeader>
        </div>

        <div
            bind:this={lintasan}
            onscroll={saatDigeser}
            class="flex min-h-0 flex-1 snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            aria-roledescription="carousel"
        >
            {#each pengumumans as pengumuman, indeks (pengumuman.id)}
                <article
                    class="w-full shrink-0 snap-center overflow-y-auto overscroll-contain px-5 pt-3 pb-4"
                    aria-roledescription="slide"
                    aria-label="{indeks + 1} dari {pengumumans.length}"
                    aria-hidden={indeks !== aktif}
                >
                    <div
                        class="relative mb-4 grid h-28 place-items-center overflow-hidden rounded-[1.5rem] {warnaSampul(
                            pengumuman.id,
                        )}"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute -top-8 -left-6 size-28 rounded-full bg-current/10"
                        ></span>
                        <span
                            class="absolute -right-6 -bottom-10 size-32 rounded-full bg-current/[0.07]"
                        ></span>
                        <Megaphone
                            class="size-11 -rotate-12"
                            strokeWidth={1.75}
                        />
                    </div>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <time datetime={pengumuman.dibuat ?? undefined}
                            >{labelWaktu(pengumuman.dibuat)}</time
                        >
                        {#if baru(pengumuman)}
                            <Badge>Baru</Badge>
                        {/if}
                    </div>
                    <h2
                        class="mt-2 text-xl leading-snug font-bold tracking-tight text-balance"
                    >
                        {pengumuman.judul}
                    </h2>
                    <p
                        class="mt-3 text-[0.9375rem] leading-relaxed break-words whitespace-pre-line"
                    >
                        {pengumuman.isi}
                    </p>
                </article>
            {/each}
        </div>

        {#if pengumumans.length > 1}
            <div
                class="flex shrink-0 items-center justify-between gap-2 border-t border-border/70 px-3 pt-2"
                style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));"
            >
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full text-foreground transition-colors hover:bg-muted disabled:opacity-30"
                    aria-label="Pengumuman sebelumnya"
                    disabled={aktif === 0}
                    onclick={() => geserKe(aktif - 1)}
                >
                    <ChevronLeft class="size-5" aria-hidden="true" />
                </button>
                <div class="flex items-center gap-1.5" aria-hidden="true">
                    {#each pengumumans as pengumuman, indeks (pengumuman.id)}
                        <span
                            class="h-1.5 rounded-full transition-all duration-200 motion-reduce:transition-none {indeks ===
                            aktif
                                ? 'w-5 bg-primary'
                                : 'w-1.5 bg-muted-foreground/25'}"
                        ></span>
                    {/each}
                </div>
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full text-foreground transition-colors hover:bg-muted disabled:opacity-30"
                    aria-label="Pengumuman berikutnya"
                    disabled={aktif === pengumumans.length - 1}
                    onclick={() => geserKe(aktif + 1)}
                >
                    <ChevronRight class="size-5" aria-hidden="true" />
                </button>
            </div>
        {:else}
            <div
                class="shrink-0"
                style="height: max(0.75rem, env(safe-area-inset-bottom));"
            ></div>
        {/if}
    </SheetContent>
</Sheet>
