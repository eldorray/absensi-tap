<?php

use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\Izin;
use App\Models\User;
use Illuminate\Database\QueryException;

test('satu guru hanya punya satu baris absensi per tanggal', function () {
    $guru = User::factory()->create();

    Absensi::factory()->for($guru)->create(['tanggal' => '2026-09-01']);

    expect(fn () => Absensi::factory()->for($guru)->create(['tanggal' => '2026-09-01']))
        ->toThrow(QueryException::class);
});

test('attempt menyimpan hasil dan koordinat', function () {
    $attempt = AbsensiAttempt::factory()->for(User::factory())->create([
        'tipe' => TipeTap::Masuk,
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy_meter' => 12,
        'jarak_meter' => 34,
        'hasil' => HasilTap::Diterima,
        'terverifikasi' => true,
    ]);

    $fresh = $attempt->fresh();

    expect($fresh->tipe)->toBe(TipeTap::Masuk)
        ->and($fresh->hasil)->toBe(HasilTap::Diterima)
        ->and($fresh->latitude)->toBe(-6.1753924)
        ->and($fresh->accuracy_meter)->toBe(12)
        ->and($fresh->terverifikasi)->toBeTrue();
});

test('absensi terhubung ke attempt masuk dan pulang', function () {
    $guru = User::factory()->create();
    $masuk = AbsensiAttempt::factory()->for($guru)->create(['tipe' => TipeTap::Masuk]);
    $pulang = AbsensiAttempt::factory()->for($guru)->create(['tipe' => TipeTap::Pulang]);

    $absensi = Absensi::factory()->for($guru)->create([
        'masuk_attempt_id' => $masuk->id,
        'pulang_attempt_id' => $pulang->id,
        'status' => StatusAbsensi::Terlambat,
    ]);

    expect($absensi->masukAttempt->id)->toBe($masuk->id)
        ->and($absensi->pulangAttempt->id)->toBe($pulang->id)
        ->and($absensi->status)->toBe(StatusAbsensi::Terlambat);
});

test('izin default pending dan punya state disetujui', function () {
    expect(Izin::factory()->for(User::factory())->create()->status)->toBe(StatusIzin::Pending)
        ->and(Izin::factory()->for(User::factory())->disetujui()->create()->status)->toBe(StatusIzin::Disetujui);
});

test('izin menyimpan rentang tanggal sebagai Carbon', function () {
    $izin = Izin::factory()->for(User::factory())->create([
        'tipe' => TipeIzin::Sakit,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-03',
    ]);

    $fresh = $izin->fresh();

    expect($fresh->tipe)->toBe(TipeIzin::Sakit)
        ->and($fresh->tanggal_mulai->toDateString())->toBe('2026-09-01')
        ->and($fresh->tanggal_selesai->toDateString())->toBe('2026-09-03');
});
