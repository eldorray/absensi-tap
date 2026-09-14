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

test('berlakuPada tidak pernah mengembalikan lebih dari satu kelas untuk tanggal yang sama, di sepanjang rentang pemindahan', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);

    $tempatkan = app(TempatkanSiswa::class);
    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    foreach (Carbon::parse('2026-07-01')->daysUntil('2026-10-15') as $tanggal) {
        $kelasBerlaku = AnggotaKelas::where('siswa_id', $siswa->id)
            ->berlakuPada($tanggal)
            ->pluck('kelas_id');

        expect($kelasBerlaku->count())->toBeLessThanOrEqual(1);

        $tanggalString = $tanggal->toDateString();
        $diDalamRentangLama = $tanggalString >= '2026-07-15' && $tanggalString <= '2026-09-30';
        $diDalamRentangBaru = $tanggalString >= '2026-10-01' && $tanggalString <= '2026-10-15';

        if ($diDalamRentangLama || $diDalamRentangBaru) {
            expect($kelasBerlaku)->toHaveCount(1);
        }
    }
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

test('berlakuPada inklusif tepat pada tanggal mulai dan tanggal selesai', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);

    $tempatkan = app(TempatkanSiswa::class);
    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    expect(AnggotaKelas::berlakuPada(Carbon::parse('2026-07-15'))->pluck('kelas_id')->all())->toBe([$lama->id])
        ->and(AnggotaKelas::berlakuPada(Carbon::parse('2026-09-30'))->pluck('kelas_id')->all())->toBe([$lama->id])
        ->and(AnggotaKelas::berlakuPada(Carbon::parse('2026-10-01'))->pluck('kelas_id')->all())->toBe([$baru->id]);
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

test('mengeluarkan siswa dengan tanggal selesai sebelum tanggal mulai ditolak', function () {
    $siswa = Siswa::factory()->create();
    $kelas = Kelas::factory()->create();
    $tempatkan = app(TempatkanSiswa::class);

    $anggota = $tempatkan($siswa, $kelas, Carbon::parse('2026-07-15'));

    expect(fn () => $tempatkan->keluarkan($anggota, Carbon::parse('2026-07-01')))
        ->toThrow(InvalidArgumentException::class);

    expect($anggota->refresh()->is_active)->toBeTrue()
        ->and($anggota->tanggal_selesai)->toBeNull();
});

test('siswa yang sudah dikeluarkan dengan tanggal masa depan tidak membuat dua kelas berlaku setelah dipindah', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);

    $tempatkan = app(TempatkanSiswa::class);
    $anggotaLama = $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan->keluarkan($anggotaLama, Carbon::parse('2026-12-31'));

    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    foreach (Carbon::parse('2026-07-15')->daysUntil('2026-11-01') as $tanggal) {
        $kelasBerlaku = AnggotaKelas::where('siswa_id', $siswa->id)
            ->berlakuPada($tanggal)
            ->pluck('kelas_id');

        expect($kelasBerlaku->count())->toBeLessThanOrEqual(1);
    }

    expect(AnggotaKelas::berlakuPada(Carbon::parse('2026-11-01'))->pluck('kelas_id')->all())->toBe([$baru->id])
        ->and($anggotaLama->refresh()->tanggal_selesai->toDateString())->toBe('2026-09-30');
});

test('menempatkan siswa mundur ke tanggal sebelum kelas yang sudah dijadwalkan ditolak', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);
    $ketiga = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5C']);

    $tempatkan = app(TempatkanSiswa::class);
    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    expect(fn () => $tempatkan($siswa, $ketiga, Carbon::parse('2026-08-20')))
        ->toThrow(InvalidArgumentException::class);

    expect(AnggotaKelas::where('siswa_id', $siswa->id)->count())->toBe(2);
});

test('menempatkan siswa di kelas lain pada tanggal yang sama ditolak', function () {
    $siswa = Siswa::factory()->create();
    $lama = Kelas::factory()->create(['kantor_id' => $siswa->kantor_id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $siswa->kantor_id, 'nama' => '5B']);
    $tempatkan = app(TempatkanSiswa::class);

    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));

    expect(fn () => $tempatkan($siswa, $baru, Carbon::parse('2026-07-15')))
        ->toThrow(InvalidArgumentException::class);

    expect(AnggotaKelas::where('siswa_id', $siswa->id)->count())->toBe(1);
});

test('menempatkan ulang di kelas yang sudah tertutup tidak diam-diam mengembalikan baris lama', function () {
    $kantor = Kantor::factory()->create();
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);
    $tempatkan = app(TempatkanSiswa::class);

    $tempatkan($siswa, $lama, Carbon::parse('2026-07-15'));
    $tempatkan($siswa, $baru, Carbon::parse('2026-10-01'));

    expect(fn () => $tempatkan($siswa, $lama, Carbon::parse('2026-07-15')))
        ->toThrow(InvalidArgumentException::class);

    expect(AnggotaKelas::where('siswa_id', $siswa->id)->count())->toBe(2);
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
