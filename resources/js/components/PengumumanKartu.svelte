<script lang="ts">
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import { Badge } from '@/components/ui/badge';
    import { labelWaktu, warnaSampul } from '@/lib/pengumuman';
    import type { Pengumuman } from '@/lib/pengumuman';
    import { cn } from '@/lib/utils';

    let {
        pengumuman,
        baru = false,
        onclick,
        class: className = '',
    }: {
        pengumuman: Pengumuman;
        baru?: boolean;
        onclick: () => void;
        class?: string;
    } = $props();
</script>

<button
    type="button"
    aria-haspopup="dialog"
    {onclick}
    class={cn(
        'group flex flex-col gap-2 rounded-[1.75rem] border border-border/70 bg-card p-2 pb-3 text-left shadow-[0_8px_24px_rgba(15,23,42,0.06)] transition-transform duration-150 active:scale-[0.97] motion-reduce:transition-none',
        className,
    )}
>
    <span
        class="relative block aspect-[4/3] overflow-hidden rounded-[1.25rem] {warnaSampul(
            pengumuman.id,
        )}"
        aria-hidden="true"
    >
        <!-- Lingkaran dekoratif: sampul tanpa foto tetap terasa hidup. -->
        <span class="absolute -top-6 -left-6 size-24 rounded-full bg-current/10"
        ></span>
        <span
            class="absolute -right-4 -bottom-8 size-28 rounded-full bg-current/[0.07]"
        ></span>
        <Megaphone
            class="absolute right-4 bottom-4 size-12 -rotate-12 opacity-80 transition-transform duration-300 group-active:-rotate-6 motion-reduce:transition-none"
            strokeWidth={1.75}
        />
        {#if baru}
            <Badge class="absolute top-2.5 left-2.5 shadow-sm">Baru</Badge>
        {/if}
    </span>
    <span class="grid gap-0.5 px-1.5">
        {#if baru}<span class="sr-only">Baru:</span>{/if}
        <span
            class="line-clamp-2 text-[0.9375rem] leading-snug font-bold text-primary"
            >{pengumuman.judul}</span
        >
        <time
            class="text-xs text-muted-foreground"
            datetime={pengumuman.dibuat ?? undefined}
            >{labelWaktu(pengumuman.dibuat)}</time
        >
    </span>
</button>
