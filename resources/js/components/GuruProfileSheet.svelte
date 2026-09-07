<script lang="ts">
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import BadgeCheck from 'lucide-svelte/icons/badge-check';
    import Camera from 'lucide-svelte/icons/camera';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import LogOut from 'lucide-svelte/icons/log-out';
    import Palette from 'lucide-svelte/icons/palette';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import UserRound from 'lucide-svelte/icons/user-round';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Sheet,
        SheetContent,
        SheetHeader,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit as securityEdit } from '@/routes/security';

    type Panel = 'menu' | 'profil' | 'tampilan';

    const user = $derived(page.props.auth.user);
    // Keamanan akun hanya untuk admin: guru mengurus perangkat dan biometriknya
    // lewat alur absensi, bukan lewat halaman setelan.
    const isAdmin = $derived(page.props.auth.isAdmin === true);

    let open = $state(false);
    let panel = $state<Panel>('menu');

    const profil = useForm<{
        name: string;
        email: string;
        avatar: File | null;
    }>({ name: '', email: '', avatar: null });
    let pratinjauAvatar = $state<string | null>(null);
    const avatarUrl = $derived(
        pratinjauAvatar ?? (user.avatar ? `/storage/${user.avatar}` : null),
    );

    const judul: Record<Panel, string> = {
        menu: 'Profil saya',
        profil: 'Edit profil',
        tampilan: 'Tampilan',
    };

    function bukaPanel(tujuan: Panel): void {
        if (tujuan === 'profil') {
            profil.name = user.name;
            profil.email = user.email;
            profil.avatar = null;
            pratinjauAvatar = null;
            profil.clearErrors();
        }

        panel = tujuan;
    }

    $effect(() => {
        if (!open) {
            panel = 'menu';
        }
    });

    function keluar(): void {
        router.flushAll();
    }
    function pilihAvatar(event: Event): void {
        const file =
            (event.currentTarget as HTMLInputElement).files?.[0] ?? null;
        profil.avatar = file;
        if (pratinjauAvatar) URL.revokeObjectURL(pratinjauAvatar);
        pratinjauAvatar = file ? URL.createObjectURL(file) : null;
    }
</script>

<Sheet bind:open>
    <SheetTrigger asChild>
        {#snippet children(props)}
            <button
                type="button"
                onclick={props.onclick}
                aria-expanded={props['aria-expanded']}
                aria-label="Buka profil"
                class="avatar-kotak avatar-kotak-header relative grid place-items-center rounded-2xl bg-primary text-sm font-extrabold text-primary-foreground shadow-[0_6px_18px_rgba(20,83,45,0.24)] ring-1 ring-white/20 transition duration-200 active:scale-95"
            >
                {#if user.avatar}<img
                        src={`/storage/${user.avatar}`}
                        alt=""
                        class="foto-avatar rounded-2xl"
                    />{:else}{user.name.charAt(0).toUpperCase()}{/if}
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
        <div class="shrink-0 bg-background px-5 pt-3 pb-3">
            <div
                class="mx-auto h-1.5 w-11 rounded-full bg-muted-foreground/20"
            ></div>
            <SheetHeader class="mt-4 mb-0 pr-10 text-left">
                <div class="flex items-center gap-2">
                    {#if panel !== 'menu'}
                        <button
                            type="button"
                            onclick={() => (panel = 'menu')}
                            aria-label="Kembali ke profil"
                            class="-ml-2 grid size-9 place-items-center rounded-full text-muted-foreground transition-colors active:bg-muted"
                        >
                            <ChevronLeft class="size-5" aria-hidden="true" />
                        </button>
                    {/if}
                    <SheetTitle class="text-xl font-bold tracking-tight"
                        >{judul[panel]}</SheetTitle
                    >
                </div>
            </SheetHeader>
        </div>

        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4"
        >
            {#if panel === 'menu'}
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
                            class="avatar-kotak avatar-kotak-kartu grid shrink-0 place-items-center rounded-[1.35rem] bg-white/16 text-2xl font-extrabold ring-1 ring-white/25 backdrop-blur-sm"
                        >
                            {#if user.avatar}<img
                                    src={`/storage/${user.avatar}`}
                                    alt=""
                                    class="foto-avatar rounded-[1.35rem]"
                                />{:else}{user.name
                                    .charAt(0)
                                    .toUpperCase()}{/if}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold">
                                {user.name}
                            </p>
                            <p
                                class="truncate text-sm text-primary-foreground/75"
                            >
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
                        >{isAdmin ? 'Admin' : 'Guru'}</span
                    >
                </div>

                <nav
                    class="overflow-hidden rounded-[1.5rem] border border-border/70 bg-card shadow-sm"
                    aria-label="Menu profil"
                >
                    <button
                        type="button"
                        onclick={() => bukaPanel('profil')}
                        class="group flex min-h-16 w-full items-center gap-3 px-4 py-3 text-left transition-colors active:bg-muted/80"
                    >
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                        >
                            <UserRound class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold"
                                >Edit profil</span
                            >
                            <span
                                class="mt-0.5 block truncate text-xs text-muted-foreground"
                                >Nama dan alamat email</span
                            >
                        </span>
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground/55 transition-transform group-active:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </button>

                    {#if isAdmin}
                        <Link
                            href={toUrl(securityEdit())}
                            class="group flex min-h-16 items-center gap-3 border-t border-border/60 px-4 py-3 transition-colors active:bg-muted/80"
                        >
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                            >
                                <ShieldCheck
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold"
                                    >Keamanan akun</span
                                >
                                <span
                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                    >Password, passkey, dan perangkat</span
                                >
                            </span>
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground/55 transition-transform group-active:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                    {/if}

                    <button
                        type="button"
                        onclick={() => bukaPanel('tampilan')}
                        class="group flex min-h-16 w-full items-center gap-3 border-t border-border/60 px-4 py-3 text-left transition-colors active:bg-muted/80"
                    >
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400"
                        >
                            <Palette class="size-5" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold">Tampilan</span
                            >
                            <span
                                class="mt-0.5 block truncate text-xs text-muted-foreground"
                                >Tema terang atau gelap</span
                            >
                        </span>
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground/55 transition-transform group-active:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </button>
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

                <p
                    class="mt-4 text-center text-[0.6875rem] text-muted-foreground"
                >
                    Absensi Guru · Data akun tersimpan dengan aman
                </p>
            {:else if panel === 'profil'}
                <form
                    class="grid gap-4"
                    onsubmit={(e) => {
                        e.preventDefault();
                        profil.submit(ProfileController.update(), {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => (panel = 'menu'),
                        });
                    }}
                >
                    <div
                        class="flex flex-col items-center gap-3 rounded-[1.5rem] bg-muted/60 p-4"
                    >
                        <div class="relative">
                            <div
                                class="avatar-kotak avatar-kotak-pratinjau grid place-items-center rounded-[1.75rem] bg-primary text-3xl font-extrabold text-primary-foreground"
                            >
                                {#if avatarUrl}<img
                                        src={avatarUrl}
                                        alt="Pratinjau foto profil"
                                        class="foto-avatar"
                                    />{:else}{user.name
                                        .charAt(0)
                                        .toUpperCase()}{/if}
                            </div>
                            <label
                                for="profil-avatar"
                                class="absolute -right-2 -bottom-2 grid size-11 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-lg ring-4 ring-background"
                                aria-label="Ganti foto profil"
                                ><Camera class="size-5" /></label
                            >
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-bold">Foto profil</p>
                            <p class="text-xs text-muted-foreground">
                                JPG, PNG, atau WebP · maks. 2 MB
                            </p>
                        </div>
                        <input
                            id="profil-avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            onchange={pilihAvatar}
                        />
                        {#if profil.errors.avatar}<p
                                class="text-xs text-destructive"
                            >
                                {profil.errors.avatar}
                            </p>{/if}
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="profil-nama">Nama lengkap</Label>
                        <Input
                            id="profil-nama"
                            autocomplete="name"
                            bind:value={profil.name}
                        />
                        {#if profil.errors.name}<p
                                class="text-xs text-destructive"
                            >
                                {profil.errors.name}
                            </p>{/if}
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="profil-email">Alamat email</Label>
                        <Input
                            id="profil-email"
                            type="email"
                            autocomplete="username"
                            bind:value={profil.email}
                        />
                        {#if profil.errors.email}<p
                                class="text-xs text-destructive"
                            >
                                {profil.errors.email}
                            </p>{/if}
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Mengganti email membuat akun perlu diverifikasi ulang.
                    </p>

                    <Button
                        type="submit"
                        class="min-h-14 w-full rounded-[1.35rem] text-base font-bold"
                        disabled={profil.processing}>Simpan perubahan</Button
                    >
                </form>
            {:else}
                <div class="grid gap-4">
                    <p class="text-sm text-muted-foreground">
                        Pilih tema tampilan aplikasi. Pilihan tersimpan di HP
                        ini.
                    </p>
                    <AppearanceTabs />
                </div>
            {/if}
        </div>

        <div
            class="h-[max(0.75rem,env(safe-area-inset-bottom))] shrink-0"
        ></div>
    </SheetContent>
</Sheet>

<style>
    .avatar-kotak {
        position: relative;
        aspect-ratio: 1 / 1;
        overflow: hidden;
    }

    .avatar-kotak-header {
        width: 2.75rem;
        min-width: 2.75rem;
        max-width: 2.75rem;
        height: 2.75rem;
        min-height: 2.75rem;
        max-height: 2.75rem;
    }

    .avatar-kotak-kartu {
        width: 4rem;
        min-width: 4rem;
        max-width: 4rem;
        height: 4rem;
        min-height: 4rem;
        max-height: 4rem;
    }

    .avatar-kotak-pratinjau {
        width: 6rem;
        min-width: 6rem;
        max-width: 6rem;
        height: 6rem;
        min-height: 6rem;
        max-height: 6rem;
    }

    .foto-avatar {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        min-width: 100%;
        max-width: 100%;
        height: 100%;
        min-height: 100%;
        max-height: 100%;
        object-fit: cover;
        object-position: center;
    }
</style>
