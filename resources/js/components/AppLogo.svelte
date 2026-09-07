<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const aplikasi = $derived(page.props.aplikasi);
    const name = $derived(aplikasi?.nama ?? page.props.name);
</script>

<div
    class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
>
    {#if aplikasi?.logo_url}
        <img src={aplikasi.logo_url} alt="" class="size-full object-contain" />
    {:else}
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    {/if}
</div>
<div class="ml-1 grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">{name}</span>
</div>
