<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import CalendarOff from 'lucide-svelte/icons/calendar-off';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import History from 'lucide-svelte/icons/history';
    import UserRound from 'lucide-svelte/icons/user-round';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as izinIndex } from '@/routes/izin';
    import { edit as profileEdit } from '@/routes/profile';
    import { index as riwayatIndex } from '@/routes/riwayat';

    const items = [
        { label: 'Absensi', href: dashboard(), icon: Fingerprint },
        { label: 'Izin', href: izinIndex(), icon: CalendarOff },
        { label: 'Profil', href: profileEdit(), icon: UserRound },
        {
            label: 'Riwayat',
            href: riwayatIndex(),
            icon: History,
        },
    ];

    function isActive(href: string): boolean {
        const currentPath = page.url.split('?')[0];

        return currentPath === href || currentPath.startsWith(`${href}/`);
    }
</script>

<nav
    aria-label="Navigasi utama guru"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border/80 bg-background/95 px-2 pt-2 shadow-[0_-8px_30px_rgba(15,23,42,0.08)] backdrop-blur-xl"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
>
    <div class="mx-auto grid max-w-lg grid-cols-4 gap-1">
        {#each items as item (item.label)}
            {@const href = toUrl(item.href)}
            <Link
                {href}
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[0.6875rem] font-semibold transition-colors {isActive(
                    href,
                )
                    ? 'bg-primary/10 text-primary'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
                aria-current={isActive(href) ? 'page' : undefined}
            >
                <item.icon
                    class="size-5"
                    strokeWidth={isActive(href) ? 2.5 : 2}
                />
                <span>{item.label}</span>
            </Link>
        {/each}
    </div>
</nav>
