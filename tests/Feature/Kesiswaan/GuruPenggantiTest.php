<?php

use App\Models\GuruKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-14 07:00:00');
    tahunAjaranAktif('2026/2027');
});

test('wali kelas mengampu kelasnya', function () {
    $wali = User::factory()->create();
    $kelas = Kelas::factory()->create(['wali_kelas_id' => $wali->id]);
    Kelas::factory()->create();

    expect(Kelas::diampuOleh($wali)->pluck('id')->all())->toBe([$kelas->id]);
});

test('guru pengganti mengampu kelas hanya selama rentangnya', function () {
    $pengganti = User::factory()->create();
    $kelas = Kelas::factory()->create();

    GuruKelas::create([
        'kelas_id' => $kelas->id,
        'user_id' => $pengganti->id,
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-20',
    ]);

    expect(Kelas::diampuOleh($pengganti, Carbon::parse('2026-09-14'))->count())->toBe(1)
        ->and(Kelas::diampuOleh($pengganti, Carbon::parse('2026-09-25'))->count())->toBe(0)
        ->and(Kelas::diampuOleh($pengganti, Carbon::parse('2026-09-01'))->count())->toBe(0);
});

test('penugasan pengganti tanpa tanggal berlaku terus', function () {
    $pengganti = User::factory()->create();
    $kelas = Kelas::factory()->create();

    GuruKelas::create([
        'kelas_id' => $kelas->id,
        'user_id' => $pengganti->id,
        'tanggal_mulai' => null,
        'tanggal_selesai' => null,
    ]);

    expect(Kelas::diampuOleh($pengganti, Carbon::parse('2027-01-01'))->count())->toBe(1);
});

test('guru tanpa penugasan tidak mengampu kelas mana pun', function () {
    Kelas::factory()->count(3)->create();

    expect(Kelas::diampuOleh(User::factory()->create())->count())->toBe(0);
});

test('satu guru tidak bisa didaftarkan dua kali sebagai pengganti di kelas yang sama', function () {
    $pengganti = User::factory()->create();
    $kelas = Kelas::factory()->create();

    GuruKelas::create(['kelas_id' => $kelas->id, 'user_id' => $pengganti->id]);

    expect(fn () => GuruKelas::create(['kelas_id' => $kelas->id, 'user_id' => $pengganti->id]))
        ->toThrow(QueryException::class);
});

test('kelas yang diampu tetap tersaring ke tahun ajaran yang dilihat', function () {
    $wali = User::factory()->create();
    Kelas::factory()->create(['wali_kelas_id' => $wali->id, 'kantor_id' => Kantor::factory()->create()->id]);

    $tahunBaru = TahunAjaran::factory()->create();
    $tahunBaru->aktifkan();
    app(TahunAjaranTerpilih::class)->lupakan();

    expect(Kelas::diampuOleh($wali)->count())->toBe(0);
});
