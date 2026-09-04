<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import BookOpen from 'lucide-svelte/icons/book-open';
    import CalendarOff from 'lucide-svelte/icons/calendar-off';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import FolderGit2 from 'lucide-svelte/icons/folder-git-2';
    import Palette from 'lucide-svelte/icons/palette';
    import Settings2 from 'lucide-svelte/icons/settings-2';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Table2 from 'lucide-svelte/icons/table-2';
    import UserRound from 'lucide-svelte/icons/user-round';
    import Users from 'lucide-svelte/icons/users';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as guruIndex } from '@/routes/admin/guru';
    import { index as adminIzinIndex } from '@/routes/admin/izin';
    import { edit as pengaturanEdit } from '@/routes/admin/pengaturan';
    import { index as rekapIndex } from '@/routes/admin/rekap';
    import { edit as appearanceEdit } from '@/routes/appearance';
    import { index as izinIndex } from '@/routes/izin';
    import { edit as profileEdit } from '@/routes/profile';
    import { edit as securityEdit } from '@/routes/security';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const isAdmin = $derived(page.props.auth.isAdmin === true);

    const mainNavItems: NavItem[] = [
        { title: 'Absensi', href: dashboard(), icon: Fingerprint },
        { title: 'Izin', href: izinIndex(), icon: CalendarOff },
    ];

    const adminNavItems: NavItem[] = [
        { title: 'Rekap', href: rekapIndex(), icon: Table2 },
        { title: 'Izin masuk', href: adminIzinIndex(), icon: CalendarOff },
        { title: 'Guru', href: guruIndex(), icon: Users },
        { title: 'Pengaturan', href: pengaturanEdit(), icon: Settings2 },
    ];

    const settingsNavItems: NavItem[] = [
        {
            title: 'Profil',
            href: profileEdit(),
            icon: UserRound,
        },
        {
            title: 'Keamanan',
            href: securityEdit(),
            icon: ShieldCheck,
        },
        {
            title: 'Tampilan',
            href: appearanceEdit(),
            icon: Palette,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/svelte-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#svelte',
            icon: BookOpen,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent class="gap-4">
        <NavMain items={mainNavItems} label="ABSENSI" />
        {#if isAdmin}
            <NavMain items={adminNavItems} label="ADMIN" />
        {/if}
        <NavMain items={settingsNavItems} label="PENGATURAN" />
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={footerNavItems} />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
