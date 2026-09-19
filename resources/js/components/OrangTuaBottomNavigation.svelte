<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import House from 'lucide-svelte/icons/house';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes/orang-tua';

    const href = toUrl(dashboard());
    const aktif = $derived(page.url.split('?')[0] === href);
</script>

<nav
    aria-label="Navigasi utama orang tua"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border/80 bg-background/95 px-3 pt-2 shadow-[0_-8px_30px_rgba(15,23,42,0.08)] backdrop-blur-xl"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
>
    <div class="mx-auto max-w-lg">
        <Link
            {href}
            aria-current={aktif ? 'page' : undefined}
            class="mx-auto flex min-h-14 max-w-40 flex-col items-center justify-center gap-1 rounded-2xl px-4 text-xs font-bold {aktif
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-muted'}"
        >
            <House
                class="size-5"
                strokeWidth={aktif ? 2.5 : 2}
                aria-hidden="true"
            />
            Beranda
        </Link>
    </div>
</nav>
