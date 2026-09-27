<script lang="ts">
    import type { Snippet } from 'svelte';
    import { setContext } from 'svelte';
    import { DIALOG_CONTEXT, type DialogContext } from './context';

    let {
        open = $bindable(false),
        onOpenChange,
        children,
    }: { open?: boolean; onOpenChange?: (value: boolean) => void; children?: Snippet } = $props();

    const uid = $props.id();
    const titleId = `dialog-judul-${uid}`;

    const context: DialogContext = {
        open: () => open,
        titleId,
        setOpen: (value: boolean) => {
            open = value;
            onOpenChange?.(value);
        },
    };

    setContext(DIALOG_CONTEXT, context);
</script>

{@render children?.()}
