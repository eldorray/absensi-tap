<?php

use App\Actions\Absensi\RekapBulanan;
use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\TipeIzin;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 10:00:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka rekap', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rekap.index'))->assertForbidden();
});

test('hari kerja yang sudah lewat tanpa jejak jadi alfa', function () {
    $guru = User::factory()->create();
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-01']['status'])->toBe('alfa');
});

test('hari ini yang belum ditap belum berstatus alfa', function () {
    $guru = User::factory()->create();
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-30']['status'])->toBe('belum');
});

test('minggu bukan hari kerja', function () {
    $guru = User::factory()->create();
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-06']['status'])->toBe('bukan_hari_kerja');
});

test('hari libur mengalahkan alfa', function () {
    $guru = User::factory()->create();
    HariLibur::factory()->create(['tanggal' => '2026-09-02', 'nama' => 'Libur Sekolah']);
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-02']['status'])->toBe('libur');
});

test('izin disetujui mengisi seluruh rentangnya', function () {
    $guru = User::factory()->create();
    Izin::factory()->for($guru)->disetujui()->create([
        'tipe' => TipeIzin::Sakit, 'tanggal_mulai' => '2026-09-03', 'tanggal_selesai' => '2026-09-04',
    ]);
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-03']['status'])->toBe('sakit')->and($hari['2026-09-04']['status'])->toBe('sakit');
});

test('izin yang masih pending tidak mengubah rekap', function () {
    $guru = User::factory()->create();
    Izin::factory()->for($guru)->create(['tanggal_mulai' => '2026-09-03', 'tanggal_selesai' => '2026-09-03']);
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-03']['status'])->toBe('alfa');
});

test('absensi tanpa biometrik ditandai anomali', function () {
    $guru = User::factory()->create();
    $attempt = AbsensiAttempt::factory()->for($guru)->create([
        'tipe' => TipeTap::Masuk, 'hasil' => HasilTap::Diterima, 'terverifikasi' => false,
        'created_at' => '2026-09-01 07:00:00',
    ]);
    Absensi::factory()->for($guru)->create([
        'tanggal' => '2026-09-01', 'status' => StatusAbsensi::Hadir, 'masuk_attempt_id' => $attempt->id,
    ]);
    $hari = collect(app(RekapBulanan::class)(2026, 9, $guru->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-01']['status'])->toBe('hadir')->and($hari['2026-09-01']['anomali'])->toContain('tanpa_biometrik');
});

test('koordinat identik antar guru di hari yang sama ditandai kembar', function () {
    $satu = User::factory()->create();
    $dua = User::factory()->create();
    foreach ([$satu, $dua] as $guru) {
        $attempt = AbsensiAttempt::factory()->for($guru)->create([
            'tipe' => TipeTap::Masuk, 'hasil' => HasilTap::Diterima, 'terverifikasi' => true,
            'latitude' => -6.1753924, 'longitude' => 106.8271528, 'created_at' => '2026-09-01 07:00:00',
        ]);
        Absensi::factory()->for($guru)->create([
            'tanggal' => '2026-09-01', 'status' => StatusAbsensi::Hadir, 'masuk_attempt_id' => $attempt->id,
        ]);
    }
    $hari = collect(app(RekapBulanan::class)(2026, 9, $satu->id)['baris'][0]['hari'])->keyBy('tanggal');
    expect($hari['2026-09-01']['anomali'])->toContain('koordinat_kembar');
});

test('ringkasan menghitung jumlah tiap status', function () {
    $guru = User::factory()->create();
    Izin::factory()->for($guru)->disetujui()->create([
        'tipe' => TipeIzin::Cuti, 'tanggal_mulai' => '2026-09-03', 'tanggal_selesai' => '2026-09-04',
    ]);
    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    expect($rekap['baris'][0]['ringkasan']['cuti'])->toBe(2);
});

test('admin melihat halaman rekap', function () {
    User::factory()->create();
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap.index', ['tahun' => 2026, 'bulan' => 9]))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/Rekap')->has('rekap.baris', 1)->has('rekap.tanggals', 30));
});

test('export CSV berisi header dan satu baris per guru', function () {
    User::factory()->create(['name' => 'Bu Aminah', 'nip' => '123']);
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap.export', ['tahun' => 2026, 'bulan' => 9]));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $isi = $response->streamedContent();
    expect($isi)->toContain('NIP')->and($isi)->toContain('Nama')->and($isi)->toContain('Bu Aminah')->and($isi)->toContain('2026-09-01');
});

test('guru tidak boleh mengekspor rekap', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.rekap.export', ['tahun' => 2026, 'bulan' => 9]))->assertForbidden();
});
