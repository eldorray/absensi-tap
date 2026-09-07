<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import GuruBottomNavigation from '@/components/GuruBottomNavigation.svelte';
    import GuruProfileSheet from '@/components/GuruProfileSheet.svelte';
    import ThemeToggle from '@/components/ThemeToggle.svelte';
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
    const tahunAjaran = $derived(page.props.tahunAjaran);
    const namaAplikasi = $derived(page.props.aplikasi?.nama ?? 'Absensi Guru');
    const userName = $derived(page.props.auth.user.name);
</script>

{#if isAdmin}
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="admin-scroll min-w-0 overflow-x-clip"
        >
            <AppSidebarHeader {breadcrumbs} />
            {#if tahunAjaran && !tahunAjaran.is_active}
                <div
                    class="mx-4 mt-4 rounded-2xl bg-[var(--g-yellow-c)] px-4 py-3 text-sm text-[var(--g-yellow-ink)] sm:mx-6"
                >
                    Sedang melihat data tahun ajaran <b>{tahunAjaran.nama}</b>,
                    bukan tahun yang aktif. Angka di halaman ini milik tahun
                    tersebut.
                </div>
            {/if}
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
                        {namaAplikasi}
                    </p>
                    <p class="max-w-56 truncate text-sm font-semibold">
                        {userName}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <ThemeToggle />
                    <GuruProfileSheet />
                </div>
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
