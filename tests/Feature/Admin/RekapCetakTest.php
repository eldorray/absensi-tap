<?php

use App\Actions\Absensi\RekapBulanan;
use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\User;
use Carbon\CarbonPeriod;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // 2026-09-07 Senin. September 2026: 30 hari, 26 hari kerja (Minggu libur).
    Carbon::setTestNow('2026-09-07 07:30:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka laporan cetak', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.rekap.cetak', ['tahun' => 2026, 'bulan' => 9]))
        ->assertForbidden();
});

test('laporan memuat komposisi hari efektif sampai persentase kehadiran', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();

    Carbon::setTestNow('2026-09-07 13:45:00');
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'pulang',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap.cetak', ['tahun' => 2026, 'bulan' => 9]))
        ->assertOk()
        ->assertViewIs('admin.rekap-cetak')
        ->assertSee('Rekap Kehadiran Guru')
        ->assertSee('September 2026')
        ->assertSee('Hari efektif')
        ->assertSee('Total kehadiran')
        ->assertSee('Absen masuk')
        ->assertSee('Absen pulang')
        ->assertSee('Terlambat')
        ->assertSee('Menit terlambat')
        ->assertSee('30 menit')
        ->assertSee('% Kehadiran')
        ->assertSee($guru->name);
});

test('hari efektif tidak menghitung hari libur, hari non-kerja, dan izin', function () {
    Carbon::setTestNow('2026-10-01 07:30:00');
    $guru = User::factory()->create();
    HariLibur::factory()->create(['tanggal' => '2026-09-17']);

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $baris = $rekap['baris'][0];

    // September 2026: 30 hari, 4 hari Minggu, 1 hari libur => 25 hari efektif.
    expect($baris['hari_efektif'])->toBe(25)
        ->and($baris['kehadiran'])->toBe(0)
        ->and($baris['persentase'])->toBe(0.0);
});

test('izin yang disetujui mengurangi hari efektif, bukan dihitung alfa', function () {
    Carbon::setTestNow('2026-10-01 07:30:00');
    $guru = User::factory()->create();
    Izin::factory()->for($guru)->create([
        'tipe' => TipeIzin::Sakit,
        'status' => StatusIzin::Disetujui,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-03',
    ]);

    $baris = app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0];

    // 30 hari - 4 Minggu - 3 hari sakit = 23.
    expect($baris['hari_efektif'])->toBe(23);
});

test('persentase kehadiran dihitung dari hari efektif', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ]);

    $baris = app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0];

    expect($baris['kehadiran'])->toBe(1)
        ->and($baris['terlambat'])->toBe(1)
        ->and($baris['menit_terlambat'])->toBe(30)
        ->and($baris['masuk'])->toBe(1)
        ->and($baris['pulang'])->toBe(0)
        ->and($baris['persentase'])->toBe(round(1 / $baris['hari_efektif'] * 100, 2));
});

test('guru tanpa hari efektif tidak memicu pembagian nol', function () {
    $guru = User::factory()->create();

    // Seluruh hari bulan itu dijadikan libur.
    foreach (range(1, 30) as $hari) {
        HariLibur::factory()->create(['tanggal' => sprintf('2026-09-%02d', $hari)]);
    }

    $baris = app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0];

    expect($baris['hari_efektif'])->toBe(0)
        ->and($baris['persentase'])->toBe(0.0);
});

test('guru berjadwal 3 hari sepekan yang selalu datang mendapat 100 persen', function () {
    Carbon::setTestNow('2026-03-01 07:30:00');
    $guru = User::factory()->create();

    // Senin, Rabu, Jumat saja.
    foreach (range(0, 6) as $day) {
        JadwalKerja::factory()->create([
            'user_id' => $guru->id,
            'day_of_week' => $day,
            'is_hari_kerja' => in_array($day, [1, 3, 5], true),
        ]);
    }

    // Februari 2026 tepat 4 pekan: 12 hari Senin/Rabu/Jumat.
    foreach (CarbonPeriod::create('2026-02-01', '2026-02-28') as $tanggal) {
        if (in_array($tanggal->dayOfWeek, [1, 3, 5], true)) {
            Absensi::factory()->for($guru)->create(['tanggal' => $tanggal->toDateString()]);
        }
    }

    $baris = app(RekapBulanan::class)(2026, 2, $guru->id)['baris'][0];

    expect($baris['hari_efektif'])->toBe(12)
        ->and($baris['kehadiran'])->toBe(12)
        ->and($baris['persentase'])->toBe(100.0);
});

test('hari yang belum tiba tidak ikut hari efektif bulan berjalan', function () {
    $guru = User::factory()->create();

    // 1-5 September 2026 (Selasa-Sabtu) hadir; hari ini 7 September belum tap.
    foreach (range(1, 5) as $hari) {
        Absensi::factory()->for($guru)->create(['tanggal' => sprintf('2026-09-%02d', $hari)]);
    }

    $baris = app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0];

    expect($baris['hari_efektif'])->toBe(5)
        ->and($baris['persentase'])->toBe(100.0);
});

test('menit terlambat tetap mengikuti jadwal saat tap walau jam masuk diundur sesudahnya', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();

    // Tap 07:30 terlambat menurut jam masuk 07:00, lalu jadwal Senin diundur.
    JadwalKerja::query()->whereNull('user_id')->where('day_of_week', 1)->update(['jam_masuk' => '08:00:00']);

    $baris = app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0];

    expect($baris['terlambat'])->toBe(1)
        ->and($baris['menit_terlambat'])->toBe(30);
});

test('laporan cetak memuat kolom izin, sakit, cuti, dan alfa seperti ringkasan layar', function () {
    $guru = User::factory()->create();
    // 1-5 September Selasa-Sabtu: sakit 1-3, sisanya (4-5) alfa. Hari ini belum.
    Izin::factory()->for($guru)->disetujui()->create([
        'tipe' => TipeIzin::Sakit,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-03',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap.cetak', ['tahun' => 2026, 'bulan' => 9]))
        ->assertOk()
        ->assertSeeInOrder(['Menit terlambat', 'Izin', 'Sakit', 'Cuti', 'Alfa', '% Kehadiran'])
        ->assertSeeInOrder([
            '<td class="angka">0 menit</td>',
            '<td class="angka">0</td>',
            '<td class="angka">3</td>',
            '<td class="angka">0</td>',
            '<td class="angka">2</td>',
        ], false);
});
