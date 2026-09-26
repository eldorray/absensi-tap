<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import FileHeart from 'lucide-svelte/icons/file-heart';
    import House from 'lucide-svelte/icons/house';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes/orang-tua';
    import { index as izinIndex } from '@/routes/orang-tua/izin';

    const items = [
        { label: 'Beranda', href: toUrl(dashboard()), icon: House },
        { label: 'Izin anak', href: toUrl(izinIndex()), icon: FileHeart },
    ];
</script>

<nav
    aria-label="Navigasi utama orang tua"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border/80 bg-background/95 px-3 pt-2 shadow-[0_-8px_30px_rgba(15,23,42,0.08)] backdrop-blur-xl"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
>
    <div class="mx-auto grid max-w-lg grid-cols-2 gap-2">
        {#each items as item (item.label)}
            {@const aktif = page.url.split('?')[0] === item.href}
            <Link
                href={item.href}
                aria-current={aktif ? 'page' : undefined}
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl px-4 text-xs font-bold transition-colors {aktif
                    ? 'bg-primary/10 text-primary'
                    : 'text-muted-foreground hover:bg-muted'}"
            >
                <item.icon
                    class="size-5"
                    strokeWidth={aktif ? 2.5 : 2}
                    aria-hidden="true"
                />
                {item.label}
            </Link>
        {/each}
    </div>
</nav>
