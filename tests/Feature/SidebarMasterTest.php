<?php

test('menu master dikelompokkan sebagai dropdown di sidebar', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));
    $dropdown = file_get_contents(resource_path('js/components/NavDropdown.svelte'));

    expect($sidebar)
        ->toContain('const masterNavItems: NavItem[] = [')
        ->toContain('label="Master"')
        // Kelima menu ini pindah ke dalam kelompok Master.
        ->toContain("title: 'Tahun ajaran'")
        ->toContain("title: 'Unit'")
        ->toContain("title: 'Guru'")
        ->toContain("title: 'Akun staf'")
        ->toContain("title: 'Orang tua'")
        ->toContain("title: 'Kelola role'");

    // Kelompoknya bisa dibuka-tutup dan terbuka sendiri saat isinya aktif.
    expect($dropdown)
        ->toContain('aria-expanded={terbuka}')
        ->toContain('dibukaManual = !terbuka')
        ->toContain('dibukaManual ?? adaYangAktif')
        // Tombolnya dirender sendiri: Tooltip.Trigger bits-ui menimpa onclick
        // yang dikirim lewat props SidebarMenuButton, jadi toggle-nya mati.
        ->toContain('{#snippet children(props)}')
        ->toContain('<button');

    // Menu harian tetap di luar dropdown supaya tidak butuh dua klik.
    $adminBlok = substr(
        $sidebar,
        strpos($sidebar, 'const adminNavItems'),
        strpos($sidebar, 'const masterNavItems') - strpos($sidebar, 'const adminNavItems'),
    );

    expect($adminBlok)
        ->toContain("title: 'Rekap harian'")
        ->toContain("title: 'Izin guru'")
        ->toContain("title: 'Izin orang tua'")
        ->not->toContain("title: 'Akun staf'");
});

test('sidebar admin tidak memuat menu absen dan izin milik guru', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));

    // Sidebar hanya dirender untuk admin; tap absen guru ada di navigasi bawah.
    expect($sidebar)
        ->not->toContain('label="ABSENSI"')
        ->not->toContain('mainNavItems')
        ->not->toContain('@/routes/izin')
        ->toContain('label="ADMIN"');

    // Navigasi guru tetap punya keduanya.
    expect(file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte')))
        ->toContain('Absensi')
        ->toContain('Izin');
});

test('rekap di Laporan Kehadiran, izin di Perlu tindakan dengan badge', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));
    $laporan = substr(
        $sidebar,
        strpos($sidebar, 'const laporanNavItems'),
        strpos($sidebar, 'const menunggu') - strpos($sidebar, 'const laporanNavItems'),
    );

    expect($laporan)
        ->toContain("title: 'Rekap harian'")
        ->toContain("title: 'Rekap bulanan'")
        ->not->toContain("title: 'Izin guru'");

    expect($sidebar)
        ->toContain('label="Laporan Kehadiran"')
        ->toContain('label="PERLU TINDAKAN"')
        ->toContain('badge: menunggu.izin_guru')
        ->toContain('badge: menunggu.izin_orang_tua');

    // Urutan: Ringkasan, Perlu tindakan, Master, Kesiswaan, Laporan Kehadiran.
    expect(strpos($sidebar, 'label="RINGKASAN"'))->toBeLessThan(strpos($sidebar, 'label="PERLU TINDAKAN"'))
        ->and(strpos($sidebar, 'label="PERLU TINDAKAN"'))->toBeLessThan(strpos($sidebar, 'label="Master"'))
        ->and(strpos($sidebar, 'label="Master"'))->toBeLessThan(strpos($sidebar, 'label="Kesiswaan"'))
        ->and(strpos($sidebar, 'label="Kesiswaan"'))->toBeLessThan(strpos($sidebar, 'label="Laporan Kehadiran"'));
});

test('tautan bawaan starter kit tidak ada di sidebar', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));

    // Repository dan Documentation menunjuk dokumentasi Laravel, bukan aplikasi
    // ini, dan tidak berguna bagi admin sekolah.
    expect($sidebar)
        ->not->toContain('footerNavItems')
        ->not->toContain('NavFooter')
        ->not->toContain('svelte-starter-kit')
        ->toContain('<NavUser />');
});
