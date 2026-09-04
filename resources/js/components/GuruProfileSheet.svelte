<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
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
            description: 'Ubah nama dan alamat email',
            href: profileEdit(),
            icon: UserRound,
        },
        {
            label: 'Keamanan akun',
            description: 'Password, passkey, dan perangkat',
            href: securityEdit(),
            icon: ShieldCheck,
        },
        {
            label: 'Tampilan',
            description: 'Atur tema terang atau gelap',
            href: appearanceEdit(),
            icon: Palette,
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
                class="grid size-10 place-items-center rounded-2xl bg-primary text-sm font-bold text-primary-foreground shadow-sm transition-transform active:scale-95"
            >
                {user.name.charAt(0).toUpperCase()}
            </button>
        {/snippet}
    </SheetTrigger>

    <SheetContent
        side="bottom"
        class="mx-auto max-h-[88svh] w-full max-w-lg gap-0 rounded-t-[2rem] px-4 pt-3 pb-0"
    >
        <div
            class="mx-auto mb-5 h-1.5 w-12 rounded-full bg-muted-foreground/25"
        ></div>

        <SheetHeader class="pr-8 text-left">
            <SheetTitle>Profil saya</SheetTitle>
        </SheetHeader>

        <div class="mt-5 flex items-center gap-4 rounded-3xl bg-muted/70 p-4">
            <div
                class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary text-lg font-bold text-primary-foreground shadow-sm"
                aria-hidden="true"
            >
                {user.name.charAt(0).toUpperCase()}
            </div>
            <div class="min-w-0">
                <p class="truncate font-bold">{user.name}</p>
                <p class="truncate text-sm text-muted-foreground">
                    {user.email}
                </p>
                <p
                    class="mt-1 text-xs font-semibold tracking-wide text-primary uppercase"
                >
                    Guru
                </p>
            </div>
        </div>

        <nav class="mt-4 grid gap-2" aria-label="Menu profil">
            {#each menuItems as item (item.label)}
                <Link
                    href={toUrl(item.href)}
                    class="flex min-h-16 items-center gap-3 rounded-2xl px-3 transition-colors hover:bg-muted"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <item.icon class="size-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold">{item.label}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{item.description}</span
                        >
                    </span>
                </Link>
            {/each}
        </nav>

        <div
            class="mt-3 border-t border-border pt-3"
            style="padding-bottom: max(1rem, env(safe-area-inset-bottom));"
        >
            <Link
                href={logout()}
                as="button"
                onclick={keluar}
                class="flex min-h-14 w-full items-center gap-3 rounded-2xl px-3 font-semibold text-destructive transition-colors hover:bg-destructive/10"
                data-test="guru-logout-button"
            >
                <span
                    class="grid size-10 place-items-center rounded-2xl bg-destructive/10"
                >
                    <LogOut class="size-5" aria-hidden="true" />
                </span>
                Keluar
            </Link>
        </div>
    </SheetContent>
</Sheet>
