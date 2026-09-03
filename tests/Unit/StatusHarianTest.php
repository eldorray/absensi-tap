<?php

use App\Enums\StatusHari;
use App\Support\StatusHarian;

test('bukan hari kerja mengalahkan semua cabang lain', function () {
    expect(StatusHarian::resolve(false, true, 'sakit', 'hadir', true))
        ->toBe(StatusHari::BukanHariKerja);
});

test('hari libur mengalahkan izin dan absensi', function () {
    expect(StatusHarian::resolve(true, true, 'sakit', 'hadir', true))
        ->toBe(StatusHari::Libur);
});

test('izin disetujui mengalahkan absensi', function () {
    expect(StatusHarian::resolve(true, false, 'sakit', 'hadir', true))
        ->toBe(StatusHari::Sakit);
});

test('absensi hadir dipakai kalau tidak ada izin', function () {
    expect(StatusHarian::resolve(true, false, null, 'hadir', true))
        ->toBe(StatusHari::Hadir);
});

test('absensi terlambat dipakai kalau tidak ada izin', function () {
    expect(StatusHarian::resolve(true, false, null, 'terlambat', true))
        ->toBe(StatusHari::Terlambat);
});

test('hari kerja yang sudah lewat tanpa jejak apa pun adalah alfa', function () {
    expect(StatusHarian::resolve(true, false, null, null, true))
        ->toBe(StatusHari::Alfa);
});

test('hari kerja yang belum lewat tanpa jejak apa pun belum berstatus', function () {
    expect(StatusHarian::resolve(true, false, null, null, false))
        ->toBe(StatusHari::Belum);
});

test('setiap status punya label untuk ditampilkan', function () {
    foreach (StatusHari::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
    }
});
