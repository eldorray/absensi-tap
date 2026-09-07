<script lang="ts">
    import Moon from 'lucide-svelte/icons/moon';
    import Sun from 'lucide-svelte/icons/sun';
    import { themeState } from '@/lib/theme.svelte';

    let { inverse = false }: { inverse?: boolean } = $props();
    const { resolvedAppearance, updateAppearance } = themeState();
    const gelap = $derived(resolvedAppearance() === 'dark');

    function gantiTema(): void {
        updateAppearance(gelap ? 'light' : 'dark');
    }
</script>

<button
    type="button"
    onclick={gantiTema}
    class:inverse
    class="theme-toggle"
    aria-label={gelap ? 'Gunakan mode terang' : 'Gunakan mode gelap'}
    title={gelap ? 'Mode terang' : 'Mode gelap'}
>
    {#if gelap}
        <Sun class="size-5" aria-hidden="true" />
    {:else}
        <Moon class="size-5" aria-hidden="true" />
    {/if}
</button>

<style>
    .theme-toggle {
        display: grid;
        place-items: center;
        width: 2.85rem;
        height: 2.85rem;
        flex-shrink: 0;
        border: 1px solid var(--g-line);
        border-radius: 0.95rem;
        background: var(--g-surface);
        color: var(--g-ink);
        transition:
            transform 0.2s var(--g-emphasized),
            background-color 0.2s var(--g-emphasized);
    }
    .theme-toggle:hover {
        background: var(--g-blue-c);
        color: var(--g-blue-ink);
        transform: translateY(-1px);
    }
    .theme-toggle:active {
        transform: scale(0.96);
    }
    .theme-toggle.inverse {
        border-color: var(--g-band-field);
        background: var(--g-band-field);
        color: var(--g-band-ink);
    }
    .theme-toggle.inverse:hover {
        background: color-mix(in srgb, var(--g-band-field) 75%, white 10%);
        color: var(--g-band-ink);
    }
</style>
