<?php

use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\PengaturanAbsensi;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Database\QueryException;

test('seeder mengisi tujuh hari dengan Minggu bukan hari kerja', function () {
    $this->seed(JadwalKerjaSeeder::class);

    expect(JadwalKerja::count())->toBe(7)
        ->and(JadwalKerja::where('day_of_week', 0)->value('is_hari_kerja'))->toBeFalsy()
        ->and(JadwalKerja::where('day_of_week', 1)->value('is_hari_kerja'))->toBeTruthy()
        ->and(JadwalKerja::where('day_of_week', 1)->value('jam_masuk'))->toBe('07:00:00')
        ->and(JadwalKerja::whereNotNull('user_id')->count())->toBe(0)
        ->and(PengaturanAbsensi::current()->toleransi_menit)->toBe(10);
});

test('seeder bisa dijalankan dua kali tanpa menduplikasi', function () {
    $this->seed(JadwalKerjaSeeder::class);
    $this->seed(JadwalKerjaSeeder::class);

    expect(JadwalKerja::count())->toBe(7);
});

test('lokasi menyimpan koordinat sebagai float dengan tujuh desimal', function () {
    $lokasi = Lokasi::factory()->create([
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
    ]);

    expect($lokasi->fresh()->latitude)->toBeFloat()->toBe(-6.1753924)
        ->and($lokasi->fresh()->longitude)->toBeFloat()->toBe(106.8271528)
        ->and($lokasi->radius_meter)->toBe(100)
        ->and($lokasi->is_active)->toBeTrue();
});

test('satu tanggal hanya boleh punya satu hari libur', function () {
    HariLibur::factory()->create(['tanggal' => '2026-08-17']);

    expect(fn () => HariLibur::factory()->create(['tanggal' => '2026-08-17']))
        ->toThrow(QueryException::class);
});

test('hari libur mengembalikan tanggal sebagai Carbon', function () {
    $libur = HariLibur::factory()->create(['tanggal' => '2026-08-17', 'nama' => 'HUT RI']);

    expect($libur->fresh()->tanggal->toDateString())->toBe('2026-08-17');
});
