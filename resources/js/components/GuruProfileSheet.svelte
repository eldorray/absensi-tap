<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import BadgeCheck from 'lucide-svelte/icons/badge-check';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import LogOut from 'lucide-svelte/icons/log-out';
    import Palette from 'lucide-svelte/icons/palette';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import UserRound from 'lucide-svelte/icons/user-round';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit as appearanceEdit } from '@/routes/appearance';
    import { edit as profileEdit } from '@/routes/profile';
    import { edit as securityEdit } from '@/routes/security';

    const user = $derived(page.props.auth.user);

    const menuItems = [
        {
            label: 'Edit profil',
            description: 'Nama dan alamat email',
            href: profileEdit(),
            icon: UserRound,
            tone: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        },
        {
            label: 'Keamanan akun',
            description: 'Password, passkey, dan perangkat',
            href: securityEdit(),
            icon: ShieldCheck,
            tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Tampilan',
            description: 'Tema terang atau gelap',
            href: appearanceEdit(),
            icon: Palette,
            tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        },
    ];

    function keluar(): void {
        router.flushAll();
    }
</script>

<Sheet>
    <SheetTrigger asChild>
        {#snippet children(props)}
            <button
                type="button"
                onclick={props.onclick}
                aria-expanded={props['aria-expanded']}
                aria-label="Buka profil"
                class="relative grid size-11 place-items-center rounded-2xl bg-primary text-sm font-extrabold text-primary-foreground shadow-[0_6px_18px_rgba(20,83,45,0.24)] ring-1 ring-white/20 transition duration-200 active:scale-95"
            >
                {user.name.charAt(0).toUpperCase()}
                <span
                    class="absolute -right-0.5 -bottom-0.5 size-3.5 rounded-full border-2 border-background bg-emerald-500"
                ></span>
            </button>
        {/snippet}
    </SheetTrigger>

    <SheetContent
        side="bottom"
        class="inset-x-0 mx-auto h-fit max-h-[78svh] w-full max-w-lg gap-0 overflow-hidden rounded-t-[2rem] border-x border-t border-border/70 bg-background px-0 pt-0 pb-0 shadow-[0_-24px_80px_rgba(15,23,42,0.24)]"
    >
        <div
            class="sticky top-0 z-10 bg-background/95 px-5 pt-3 pb-3 backdrop-blur-xl"
        >
            <div
                class="mx-auto h-1.5 w-11 rounded-full bg-muted-foreground/20"
            ></div>
            <SheetHeader class="mt-4 mb-0 text-left">
                <SheetTitle class="text-xl font-bold tracking-tight"
                    >Profil saya</SheetTitle
                >
            </SheetHeader>
        </div>

        <div class="overflow-y-auto px-4 pb-4">
            <section
                class="relative overflow-hidden rounded-[1.75rem] bg-primary p-5 text-primary-foreground shadow-sm"
            >
                <div
                    class="absolute -top-12 -right-10 size-36 rounded-full bg-white/10"
                ></div>
                <div
                    class="absolute -right-4 -bottom-16 size-28 rounded-full bg-black/5"
                ></div>

                <div class="relative flex items-center gap-4">
                    <div
                        class="grid size-16 shrink-0 place-items-center rounded-[1.35rem] bg-white/16 text-2xl font-extrabold ring-1 ring-white/25 backdrop-blur-sm"
                    >
                        {user.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-lg font-bold">{user.name}</p>
                        <p class="truncate text-sm text-primary-foreground/75">
                            {user.email}
                        </p>
                        <div
                            class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-primary-foreground/90"
                        >
                            <BadgeCheck class="size-4" aria-hidden="true" />
                            Akun terverifikasi
                        </div>
                    </div>
                </div>
            </section>

            <div class="mt-6 mb-2 flex items-center justify-between px-1">
                <p
                    class="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Pengaturan akun
                </p>
                <span
                    class="rounded-full bg-primary/10 px-2.5 py-1 text-[0.625rem] font-bold tracking-wide text-primary uppercase"
                    >Guru</span
                >
            </div>

            <nav
                class="overflow-hidden rounded-[1.5rem] border border-border/70 bg-card shadow-sm"
                aria-label="Menu profil"
            >
                {#each menuItems as item, index (item.label)}
                    <Link
                        href={toUrl(item.href)}
                        class="group flex min-h-16 items-center gap-3 px-4 py-3 transition-colors active:bg-muted/80 {index >
                        0
                            ? 'border-t border-border/60'
                            : ''}"
                    >
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl {item.tone}"
                        >
                            <item.icon class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold"
                                >{item.label}</span
                            >
                            <span
                                class="mt-0.5 block truncate text-xs text-muted-foreground"
                                >{item.description}</span
                            >
                        </span>
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground/55 transition-transform group-active:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                {/each}
            </nav>

            <Link
                href={logout()}
                as="button"
                onclick={keluar}
                class="mt-4 flex min-h-16 w-full items-center justify-center gap-2.5 rounded-[1.35rem] border border-destructive/15 bg-destructive/8 px-4 font-bold text-destructive transition-colors active:bg-destructive/15"
                data-test="guru-logout-button"
            >
                <LogOut class="size-5" aria-hidden="true" />
                Keluar dari akun
            </Link>

            <p class="mt-4 text-center text-[0.6875rem] text-muted-foreground">
                Absensi Guru · Data akun tersimpan dengan aman
            </p>
        </div>

        <div class="h-[max(0.75rem,env(safe-area-inset-bottom))]"></div>
    </SheetContent>
</Sheet>
