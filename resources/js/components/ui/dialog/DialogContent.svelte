<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT, type DialogContext } from './context';
    import { focusTrap } from './focus-trap';

    let { class: className = '', children }: { class?: string; children?: Snippet } =
        $props();

    const { open, setOpen, titleId } =
        getContext<DialogContext>(DIALOG_CONTEXT);

    const close = () => setOpen(false);
</script>

{#if open()}
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Tidak masuk urutan Tab: tombol X di dalam dialog sudah melayani keyboard. -->
        <button
            type="button"
            tabindex="-1"
            class="fixed inset-0 bg-black/50"
            aria-label="Tutup"
            onclick={close}
        ></button>
        <div
            class={cn(
                'relative z-10 w-[calc(100%-2rem)] max-w-lg rounded-[28px] border bg-background p-6 shadow-lg outline-none',
                className,
            )}
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            tabindex="-1"
            use:focusTrap={{ onEscape: close }}
        >
            {@render children?.()}
            <!-- Diletakkan terakhir supaya fokus awal jatuh ke isi dialog, tapi
                 tampil di pojok kanan atas. Area sentuh 44px. -->
            <button
                type="button"
                class="absolute top-2 right-2 grid size-11 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/45 focus-visible:outline-none motion-reduce:transition-none"
                aria-label="Tutup"
                onclick={close}
            >
                <X class="size-5" aria-hidden="true" />
            </button>
        </div>
    </div>
{/if}
