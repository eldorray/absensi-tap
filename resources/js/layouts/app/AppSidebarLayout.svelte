<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import GuruBottomNavigation from '@/components/GuruBottomNavigation.svelte';
    import GuruProfileSheet from '@/components/GuruProfileSheet.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    const isAdmin = $derived(page.props.auth.isAdmin === true);
    const userName = $derived(page.props.auth.user.name);
</script>

{#if isAdmin}
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader {breadcrumbs} />
            {@render children?.()}
        </AppContent>
        <Toaster />
    </AppShell>
{:else}
    <div class="min-h-svh bg-muted/35 pb-24">
        <header
            class="sticky top-0 z-40 border-b border-border/70 bg-background/90 backdrop-blur-xl"
            style="padding-top: env(safe-area-inset-top);"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-lg items-center justify-between px-4"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.14em] text-primary uppercase"
                    >
                        Absensi Guru
                    </p>
                    <p class="max-w-56 truncate text-sm font-semibold">
                        {userName}
                    </p>
                </div>
                <GuruProfileSheet />
            </div>
        </header>

        <main
            class="mx-auto min-h-[calc(100svh-4rem)] w-full max-w-lg overflow-x-clip"
        >
            {@render children?.()}
        </main>

        <GuruBottomNavigation />
        <Toaster />
    </div>
{/if}
