<script lang="ts">
    import { page } from '@inertiajs/svelte';

    let { class: className = '' }: { class?: string } = $props();

    const versi = $derived(page.props.versi);
</script>

<p
    class="px-2 py-1 text-center text-[0.6875rem] text-muted-foreground {className}"
>
    v{versi} &middot;
    <a
        href="https://fahmiealkhudhorie.site/"
        target="_blank"
        rel="noopener noreferrer"
        class="font-medium underline-offset-2 hover:text-foreground hover:underline"
    >
        Crafted by F.A.K
    </a>
</p>
