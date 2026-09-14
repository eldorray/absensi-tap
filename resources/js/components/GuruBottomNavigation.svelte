<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CalendarOff from 'lucide-svelte/icons/calendar-off';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import MoreHorizontal from 'lucide-svelte/icons/ellipsis';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import History from 'lucide-svelte/icons/history';
    import UsersRound from 'lucide-svelte/icons/users-round';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as absensiSiswaIndex } from '@/routes/absensi-siswa';
    import { index as izinIndex } from '@/routes/izin';
    import { index as jadwalIndex } from '@/routes/jadwal';
    import { index as kelasSayaIndex } from '@/routes/kelas-saya';
    import { index as riwayatIndex } from '@/routes/riwayat';

    let terbuka = $state(false);

    const utama = [
        { label: 'Absensi', href: dashboard(), icon: Fingerprint },
        { label: 'Izin', href: izinIndex(), icon: CalendarOff },
        { label: 'Jadwal', href: jadwalIndex(), icon: CalendarClock },
    ];

    const punyaKelas = $derived(page.props.auth.punyaKelas === true);
    const lainnyaAktif = $derived(
        isActive(toUrl(riwayatIndex())) ||
            (punyaKelas &&
                (isActive(toUrl(kelasSayaIndex())) ||
                    isActive(toUrl(absensiSiswaIndex())))),
    );

    function isActive(href: string): boolean {
        const currentPath = page.url.split('?')[0];

        return currentPath === href || currentPath.startsWith(`${href}/`);
    }
</script>

{#if terbuka}
    <button
        type="button"
        aria-label="Tutup menu lainnya"
        class="fixed inset-0 z-40 bg-black/20 backdrop-blur-[1px]"
        onclick={() => (terbuka = false)}
    ></button>

    <section
        class="fixed right-3 bottom-[calc(5rem+env(safe-area-inset-bottom))] left-3 z-50 mx-auto max-w-md rounded-[1.75rem] border border-border/80 bg-background p-2 shadow-[0_20px_60px_rgba(15,23,42,0.22)]"
        aria-label="Menu lainnya"
    >
        <Link
            href={toUrl(riwayatIndex())}
            onclick={() => (terbuka = false)}
            class="flex min-h-14 items-center gap-3 rounded-2xl px-3 font-semibold {isActive(
                toUrl(riwayatIndex()),
            )
                ? 'bg-primary/10 text-primary'
                : 'hover:bg-muted'}"
        >
            <span class="grid size-10 place-items-center rounded-2xl bg-muted"
                ><History class="size-5" /></span
            >
            Riwayat
        </Link>

        {#if punyaKelas}
            <Link
                href={toUrl(kelasSayaIndex())}
                onclick={() => (terbuka = false)}
                class="flex min-h-14 items-center gap-3 rounded-2xl px-3 font-semibold {isActive(
                    toUrl(kelasSayaIndex()),
                )
                    ? 'bg-primary/10 text-primary'
                    : 'hover:bg-muted'}"
            >
                <span
                    class="grid size-10 place-items-center rounded-2xl bg-muted"
                    ><UsersRound class="size-5" /></span
                >
                <span
                    >Kelas Saya<span
                        class="block text-xs font-normal text-muted-foreground"
                        >Daftar kelas dan siswa</span
                    ></span
                >
            </Link>
            <Link
                href={toUrl(absensiSiswaIndex())}
                onclick={() => (terbuka = false)}
                class="flex min-h-14 items-center gap-3 rounded-2xl px-3 font-semibold hover:bg-muted"
            >
                <span
                    class="grid size-10 place-items-center rounded-2xl bg-muted"
                    ><ClipboardCheck class="size-5" /></span
                >
                <span
                    >Absensi Siswa<span
                        class="block text-xs font-normal text-muted-foreground"
                        >Periksa kehadiran kelas</span
                    ></span
                >
            </Link>
        {/if}
    </section>
{/if}

<nav
    aria-label="Navigasi utama guru"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border/80 bg-background/95 px-2 pt-2 shadow-[0_-8px_30px_rgba(15,23,42,0.08)] backdrop-blur-xl"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
>
    <div class="mx-auto grid max-w-lg grid-cols-4 gap-1">
        {#each utama as item (item.label)}
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

        <button
            type="button"
            aria-label="Buka menu lainnya"
            aria-expanded={terbuka}
            onclick={() => (terbuka = !terbuka)}
            class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[0.6875rem] font-semibold transition-colors {terbuka ||
            lainnyaAktif
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
        >
            <MoreHorizontal
                class="size-5"
                strokeWidth={terbuka || lainnyaAktif ? 2.5 : 2}
            />
            <span>Lainnya</span>
        </button>
    </div>
</nav>
