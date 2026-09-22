<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import BookUser from 'lucide-svelte/icons/book-user';
    import Building2 from 'lucide-svelte/icons/building-2';
    import CalendarCheck from 'lucide-svelte/icons/calendar-check';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CalendarOff from 'lucide-svelte/icons/calendar-off';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';
    import Database from 'lucide-svelte/icons/database';
    import GraduationCap from 'lucide-svelte/icons/graduation-cap';
    import LayoutDashboard from 'lucide-svelte/icons/layout-dashboard';
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import Palette from 'lucide-svelte/icons/palette';
    import School from 'lucide-svelte/icons/school';
    import Settings2 from 'lucide-svelte/icons/settings-2';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Table2 from 'lucide-svelte/icons/table-2';
    import UserCog from 'lucide-svelte/icons/user-cog';
    import UserRound from 'lucide-svelte/icons/user-round';
    import Users from 'lucide-svelte/icons/users';
    import UsersRound from 'lucide-svelte/icons/users-round';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import AppVersion from '@/components/AppVersion.svelte';
    import NavDropdown from '@/components/NavDropdown.svelte';
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
    import { index as absensiSiswaIndex } from '@/routes/absensi-siswa';
    import { dashboard as adminDashboard } from '@/routes/admin';
    import { index as guruIndex } from '@/routes/admin/guru';
    import { index as adminIzinIndex } from '@/routes/admin/izin';
    import { index as jadwalGuruIndex } from '@/routes/admin/jadwal-guru';
    import { index as kantorIndex } from '@/routes/admin/kantor';
    import { index as kelasIndex } from '@/routes/admin/kelas';
    import { index as orangTuaIndex } from '@/routes/admin/orang-tua';
    import { edit as pengaturanEdit } from '@/routes/admin/pengaturan';
    import { index as pengumumanIndex } from '@/routes/admin/pengumuman';
    import { index as rekapIndex } from '@/routes/admin/rekap';
    import { index as rekapHarianIndex } from '@/routes/admin/rekap-harian';
    import { index as roleIndex } from '@/routes/admin/role';
    import { index as siswaIndex } from '@/routes/admin/siswa';
    import { index as tahunAjaranIndex } from '@/routes/admin/tahun-ajaran';
    import { index as userIndex } from '@/routes/admin/user';
    import { edit as appearanceEdit } from '@/routes/appearance';
    import { edit as profileEdit } from '@/routes/profile';
    import { edit as securityEdit } from '@/routes/security';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const isAdmin = $derived(page.props.auth.isAdmin === true);

    const adminNavItems: NavItem[] = [
        { title: 'Jadwal guru', href: jadwalGuruIndex(), icon: CalendarClock },
        { title: 'Pengumuman', href: pengumumanIndex(), icon: Megaphone },
        { title: 'Pengaturan', href: pengaturanEdit(), icon: Settings2 },
    ];

    /** Yang dibaca admin untuk memantau kehadiran, bukan untuk mengubah data. */
    const laporanNavItems: NavItem[] = [
        {
            title: 'Rekap harian',
            href: rekapHarianIndex(),
            icon: CalendarCheck,
        },
        { title: 'Rekap bulanan', href: rekapIndex(), icon: Table2 },
        { title: 'Izin masuk', href: adminIzinIndex(), icon: CalendarOff },
    ];

    const dashboardNavItems: NavItem[] = [
        { title: 'Dashboard', href: adminDashboard(), icon: LayoutDashboard },
    ];

    /**
     * Data yang jarang disentuh tapi mengikat yang lain. Dikelompokkan supaya
     * menu harian tidak tenggelam di antara sebelas entri.
     */
    const masterNavItems: NavItem[] = [
        {
            title: 'Tahun ajaran',
            href: tahunAjaranIndex(),
            icon: GraduationCap,
        },
        { title: 'Kantor', href: kantorIndex(), icon: Building2 },
        { title: 'Guru', href: guruIndex(), icon: Users },
        { title: 'Akun staf', href: userIndex(), icon: UserCog },
        { title: 'Orang tua', href: orangTuaIndex(), icon: UsersRound },
        { title: 'Kelola role', href: roleIndex(), icon: ShieldCheck },
    ];

    const kesiswaanNavItems: NavItem[] = [
        {
            title: 'Absensi siswa',
            href: absensiSiswaIndex(),
            icon: ClipboardCheck,
        },
        { title: 'Siswa', href: siswaIndex(), icon: BookUser },
        { title: 'Kelas', href: kelasIndex(), icon: School },
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
        <!--
            Tanpa kelompok ABSENSI: sidebar ini hanya dirender untuk admin
            (lihat AppSidebarLayout), dan tap absen milik guru ada di navigasi
            bawah aplikasinya sendiri.
        -->
        {#if isAdmin}
            <NavMain items={dashboardNavItems} label="RINGKASAN" />
            <NavDropdown
                label="Master"
                icon={Database}
                items={masterNavItems}
            />
            <NavDropdown
                label="Kesiswaan"
                icon={School}
                items={kesiswaanNavItems}
            />
            <NavDropdown
                label="Laporan Kehadiran"
                icon={ClipboardList}
                items={laporanNavItems}
            />
            <NavMain items={adminNavItems} label="ADMIN" />
        {/if}
        <NavMain items={settingsNavItems} label="PENGATURAN" />
    </SidebarContent>

    <SidebarFooter>
        <!-- Tautan Repository dan Documentation bawaan starter kit dibuang:
             itu dokumentasi Laravel, bukan aplikasi ini. -->
        <NavUser />
        <AppVersion />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
