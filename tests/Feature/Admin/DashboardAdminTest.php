<?php

use App\Enums\HasilTap;
use App\Enums\StatusIzin;
use App\Models\Izin;
use App\Models\Kantor;
use App\Models\Perangkat;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 07:30:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka dashboard admin', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
});

test('dashboard menampilkan ringkasan hari ini, master, dan log', function () {
    Kantor::factory()->create();
    User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/Dashboard')
            ->where('tanggal', '2026-09-07')
            ->where('ringkasanHariIni.belum', 1)
            ->where('master.guru', 1)
            ->where('master.admin', 1)
            ->where('master.kantor', 1)
            ->has('log', 0)
            ->has('bulanIni')
            ->has('labelAnomali'));
});

test('angka hari ini sama dengan rekap harian', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();

    $admin = User::factory()->admin()->create();

    $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'));
    $rekap = $this->actingAs($admin)->get(route('admin.rekap-harian.index'));

    $dashboard->assertInertia(fn ($p) => $p->where('ringkasanHariIni.terlambat', 1));
    $rekap->assertInertia(fn ($p) => $p->where('rekap.ringkasan.terlambat', 1));
});

test('log memuat tap yang diterima maupun yang ditolak', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    // Ditolak: HP tak terdaftar.
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => (string) Str::uuid(),
    ])->assertSessionHasErrors('tap');

    // Diterima.
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p->has('log', 2)
            // Terbaru di atas.
            ->where('log.0.hasil', HasilTap::Diterima->value)
            ->where('log.0.diterima', true)
            ->where('log.0.nama', $guru->name)
            ->where('log.1.hasil', HasilTap::PerangkatAsing->value)
            ->where('log.1.diterima', false));
});

test('hal yang perlu tindakan dihitung', function () {
    $guru = User::factory()->create();
    Perangkat::factory()->for($guru)->create();
    Perangkat::factory()->for($guru)->pending()->create();
    Izin::factory()->for($guru)->create(['status' => StatusIzin::Pending]);
    User::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p
            ->where('perluTindakan.izin_menunggu', 1)
            ->where('perluTindakan.perangkat_menunggu', 1)
            ->where('perluTindakan.guru_nonaktif', 1)
            ->where('perluTindakan.guru_tanpa_kantor', 2));
});

test('dashboard hanya menghitung data tahun ajaran yang sedang dilihat', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ]);

    $baru = TahunAjaran::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tahun-ajaran.aktifkan', $baru));

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p->has('log', 0)
            ->where('bulanIni.hadir', 0)
            ->where('bulanIni.terlambat', 0));
});

test('menu dashboard ada di atas kelompok master', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));

    expect($sidebar)->toContain('dashboardNavItems')
        ->and(strpos($sidebar, 'label="RINGKASAN"'))
        ->toBeLessThan(strpos($sidebar, 'label="Master"'));
});
