<?php

use App\Enums\Role;
use App\Models\AnggotaKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function berkasSiswa(string $isi): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'siswa').'.csv';
    file_put_contents($path, $isi);

    return new UploadedFile($path, 'siswa.csv', 'text/csv', null, true);
}

test('admin mengimpor siswa dari csv', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'berkas' => berkasSiswa("nama,nis,nisn,jenis_kelamin,tempat_lahir,tanggal_lahir,kelas\nAisyah Putri,20260001,0091234567,P,Tangerang,2015-04-11,5A\nBudi Santoso,20260002,,L,Serang,,5A\n"),
        ])
        ->assertRedirect(route('admin.siswa.index'));

    expect(Siswa::count())->toBe(2)
        ->and(Siswa::where('nis', '20260001')->value('nama'))->toBe('Aisyah Putri')
        ->and(Siswa::where('nis', '20260001')->value('tempat_lahir'))->toBe('Tangerang')
        ->and(Siswa::where('nis', '20260002')->value('nisn'))->toBeNull();
});

test('csv dengan pemisah titik koma dari excel indonesia tetap terbaca', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'berkas' => berkasSiswa("nama;nis;jenis_kelamin;kelas\nSiti Aminah;20260003;P;5A\n"),
        ]);

    expect(Siswa::where('nis', '20260003')->exists())->toBeTrue();
});

test('baris salah dilewati dan dilaporkan, baris benar tetap dibuat', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'berkas' => berkasSiswa("nama,nis,jenis_kelamin,kelas\nSalah,20260001,X,5A\nBaru,20260009,P,5A\n"),
        ])
        ->assertSessionHas('impor_siswa', fn (array $hasil): bool => $hasil['dibuat'] === 1
            && $hasil['dilewati'] === 1
            && str_contains($hasil['galat'][0], 'Baris 2'));

    expect(Siswa::where('nis', '20260009')->exists())->toBeTrue();
});

test('nis kembar di dalam satu berkas ditolak', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'berkas' => berkasSiswa("nama,nis,jenis_kelamin,kelas\nSatu,20260005,L,5A\nDua,20260005,P,5A\n"),
        ]);

    expect(Siswa::where('nis', '20260005')->count())->toBe(1);
});

test('kelas typo membatalkan seluruh impor termasuk baris valid sebelumnya', function () {
    tahunAjaranAktif('2026/2027');
    $kantor = Kantor::factory()->create();
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.siswa.impor'), [
        'kantor_id' => $kantor->id,
        'berkas' => berkasSiswa("nama,nis,kelas\nValid,001,5A\nTypo,002,5AA\n"),
    ])->assertSessionHasErrors('berkas');
    expect(Siswa::count())->toBe(0);
    expect(AnggotaKelas::count())->toBe(0);
});

test('kolom kelas wajib pada csv', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.siswa.impor'), [
        'kantor_id' => Kantor::factory()->create()->id,
        'berkas' => berkasSiswa("nama,nis\nValid,001\n"),
    ])->assertSessionHasErrors('berkas');
});

test('guru tidak boleh mengimpor siswa', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => Kantor::factory()->create()->id,
            'berkas' => berkasSiswa("nama,nis\nX,1\n"),
        ])
        ->assertForbidden();
});

test('template bisa diunduh dan memuat kolom yang benar', function () {
    $isi = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.siswa.template'))
        ->assertOk()
        ->streamedContent();

    expect($isi)->toContain('nama,nis,nisn,jenis_kelamin,tempat_lahir,tanggal_lahir');
});

test('nis yang sudah terdaftar diperbarui dan dipindah ke kelas di berkas', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    $kelasBaru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '6A']);
    $siswa = Siswa::factory()->create([
        'kantor_id' => $kantor->id,
        'nis' => '20260001',
        'nama' => 'Aisyah',
        'tempat_lahir' => 'Tangerang',
        'is_active' => false,
    ]);
    $lama = AnggotaKelas::factory()->create(['siswa_id' => $siswa->id, 'tanggal_mulai' => '2025-07-14']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'berkas' => berkasSiswa("nama,nis,tempat_lahir,kelas\nAisyah Putri,20260001,,6A\n"),
        ])
        ->assertSessionHas('impor_siswa', fn (array $hasil): bool => $hasil['dibuat'] === 0 && $hasil['diperbarui'] === 1);

    $siswa->refresh();

    expect(Siswa::count())->toBe(1)
        ->and($siswa->nama)->toBe('Aisyah Putri')
        // Sel kosong tidak menghapus isian lama.
        ->and($siswa->tempat_lahir)->toBe('Tangerang')
        ->and($siswa->is_active)->toBeTrue()
        ->and($lama->refresh()->tanggal_selesai)->not->toBeNull()
        ->and(AnggotaKelas::where('siswa_id', $siswa->id)->whereNull('tanggal_selesai')->value('kelas_id'))->toBe($kelasBaru->id);
});

test('opsi nonaktifkan menonaktifkan siswa yang hilang dari berkas beserta orang tua tanpa anak aktif', function () {
    $kantor = Kantor::factory()->create();
    $kantorLain = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $lulus = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '20250001', 'nama' => 'Lulus']);
    $adik = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '20260002']);
    $unitLain = Siswa::factory()->create(['kantor_id' => $kantorLain->id, 'nis' => '30000001']);
    $waliLulus = User::factory()->create(['role' => Role::OrangTua]);
    $waliKakakAdik = User::factory()->create(['role' => Role::OrangTua]);
    $lulus->orangTuas()->attach([$waliLulus->id, $waliKakakAdik->id]);
    $adik->orangTuas()->attach($waliKakakAdik->id);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'nonaktifkan_yang_hilang' => '1',
            'berkas' => berkasSiswa("nama,nis,kelas\nAdik,20260002,5A\n"),
        ])
        ->assertSessionHas('impor_siswa', fn (array $hasil): bool => $hasil['dinonaktifkan'] === ['20250001 – Lulus']
            && $hasil['orang_tua_dinonaktifkan'] === 1);

    expect($lulus->refresh()->is_active)->toBeFalse()
        ->and($adik->refresh()->is_active)->toBeTrue()
        ->and($unitLain->refresh()->is_active)->toBeTrue()
        ->and($waliLulus->refresh()->is_active)->toBeFalse()
        // Masih punya anak aktif.
        ->and($waliKakakAdik->refresh()->is_active)->toBeTrue();
});

test('tanpa opsi nonaktifkan, siswa yang tidak ada di berkas tetap aktif', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $lain = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '20250001']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'berkas' => berkasSiswa("nama,nis,kelas\nBaru,20260002,5A\n"),
        ]);

    expect($lain->refresh()->is_active)->toBeTrue();
});

test('siswa yang barisnya gagal validasi tidak ikut dinonaktifkan', function () {
    $kantor = Kantor::factory()->create();
    tahunAjaranAktif('2026/2027');
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $salahKetik = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '20250001']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.impor'), [
            'kantor_id' => $kantor->id,
            'nonaktifkan_yang_hilang' => '1',
            'berkas' => berkasSiswa("nama,nis,jenis_kelamin,kelas\nSalah Ketik,20250001,X,5A\nBaru,20260002,L,5A\n"),
        ]);

    expect($salahKetik->refresh()->is_active)->toBeTrue();
});
