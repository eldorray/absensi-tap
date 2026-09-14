<?php

use App\Actions\Kesiswaan\TempatkanSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-14 07:00:00');
    tahunAjaranAktif('2026/2027');
});

test('menempatkan siswa membuat satu keanggotaan aktif', function () {
    $siswa = Siswa::factory()->create();
    $kelas = Kelas::factory()->create();

    $anggota = app(TempatkanSiswa::class)($siswa, $kelas, Carbon::parse('2026-07-15'));

    expect($anggota->is_active)->toBeTrue()
        ->and($anggota->tanggal_selesai)->toBeNull()
        ->and(AnggotaKelas::count())->toBe(1);
});

test('pindah kelas menutup keanggotaan lama, tidak menghapusnya', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);

    $tempatkan = app(TempatkanSiswa::class);
    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    $riwayat = AnggotaKelas::where('siswa_id', $siswa->id)->orderBy('tanggal_mulai')->get();

    expect($riwayat)->toHaveCount(2)
        ->and($riwayat[0]->kelas_id)->toBe($lama->id)
        ->and($riwayat[0]->is_active)->toBeFalse()
        ->and($riwayat[0]->tanggal_selesai->toDateString())->toBe('2026-09-30')
        ->and($riwayat[1]->kelas_id)->toBe($baru->id)
        ->and($riwayat[1]->is_active)->toBeTrue();
});

test('siswa tidak pernah punya dua keanggotaan aktif sekaligus', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $tempatkan = app(TempatkanSiswa::class);

    $tempatkan($siswa, Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']), Carbon::parse('2026-07-15'));
    $tempatkan($siswa, Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']), Carbon::parse('2026-10-01'));

    expect(AnggotaKelas::where('siswa_id', $siswa->id)->where('is_active', true)->count())->toBe(1);
});

test('keanggotaan yang berlaku dihitung dari tanggal, bukan dari daftar hari ini', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);

    $tempatkan = app(TempatkanSiswa::class);
    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    $bulanLalu = AnggotaKelas::berlakuPada(Carbon::parse('2026-08-20'))->pluck('kelas_id');
    $sekarang = AnggotaKelas::berlakuPada(Carbon::parse('2026-11-20'))->pluck('kelas_id');

    expect($bulanLalu->all())->toBe([$lama->id])
        ->and($sekarang->all())->toBe([$baru->id]);
});

test('mengeluarkan siswa menutup keanggotaan tanpa memindahkannya', function () {
    $siswa = Siswa::factory()->create();
    $kelas = Kelas::factory()->create();
    $tempatkan = app(TempatkanSiswa::class);

    $anggota = $tempatkan($siswa, $kelas, Carbon::parse('2026-07-15'));
    $tempatkan->keluarkan($anggota, Carbon::parse('2026-12-31'));

    expect($anggota->refresh()->is_active)->toBeFalse()
        ->and($anggota->tanggal_selesai->toDateString())->toBe('2026-12-31')
        ->and(AnggotaKelas::count())->toBe(1);
});

test('menempatkan ulang di kelas dan tanggal yang sama tidak menggandakan baris', function () {
    $siswa = Siswa::factory()->create();
    $kelas = Kelas::factory()->create();
    $tempatkan = app(TempatkanSiswa::class);

    $pertama = $tempatkan($siswa, $kelas, Carbon::parse('2026-07-15'));
    $kedua = $tempatkan($siswa, $kelas, Carbon::parse('2026-07-15'));

    expect($kedua->id)->toBe($pertama->id)
        ->and(AnggotaKelas::count())->toBe(1);
});
