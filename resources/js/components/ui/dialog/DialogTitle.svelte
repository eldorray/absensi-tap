<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT, type DialogContext } from './context';

    let { class: className = '', children }: { class?: string; children?: Snippet } =
        $props();

    const context = getContext<DialogContext | undefined>(DIALOG_CONTEXT);
</script>

<!-- pr-10 memberi ruang untuk tombol tutup (X) di pojok kanan atas dialog. -->
<h2
    id={context?.titleId}
    class={cn('pr-10 text-lg leading-none font-semibold tracking-tight', className)}
>
    {@render children?.()}
</h2>
