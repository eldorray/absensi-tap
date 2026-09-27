<?php

use App\Enums\Role;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Berkas CSV sementara untuk pengujian impor akun orang tua.
 */
function berkasOrangTua(string $isi, string $nama = 'orang-tua.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'orang-tua').'.csv';
    file_put_contents($path, $isi);

    return new UploadedFile($path, $nama, 'text/csv', null, true);
}

test('admin mengunduh template impor orang tua', function () {
    $isi = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.orang-tua.template'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($isi)->toContain('nama,email,password')
        ->and($isi)->toContain('Siti Aminah');
});

test('admin mengimpor akun orang tua aktif dan terverifikasi dari csv', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("nama,email,password\nWali Aisyah,wali.aisyah@example.test,RahasiaKuat123\n"),
        ])
        ->assertRedirect(route('admin.orang-tua.index'));

    $orangTua = User::query()->where('email', 'wali.aisyah@example.test')->firstOrFail();

    expect($orangTua->name)->toBe('Wali Aisyah')
        ->and($orangTua->role)->toBe(Role::OrangTua)
        ->and($orangTua->is_active)->toBeTrue()
        ->and($orangTua->email_verified_at)->not->toBeNull()
        ->and(Hash::check('RahasiaKuat123', $orangTua->password))->toBeTrue()
        ->and(session('impor_orang_tua.dibuat'))->toBe(1);
});

test('email dan password kosong dibuatkan otomatis dan dikembalikan sekali', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("nama\nWali Aisyah\nWali Budi\n"),
        ])
        ->assertRedirect(route('admin.orang-tua.index'));

    $hasil = session('impor_orang_tua');
    $aisyah = User::query()->where('name', 'Wali Aisyah')->firstOrFail();
    $budi = User::query()->where('name', 'Wali Budi')->firstOrFail();

    expect($hasil['dibuat'])->toBe(2)
        ->and($hasil['dilewati'])->toBe(0)
        ->and($hasil['akun'])->toHaveCount(2)
        ->and($aisyah->email)->toBe('wali.aisyah@sekolah.local')
        ->and($budi->email)->toBe('wali.budi@sekolah.local')
        ->and(Hash::check($hasil['akun'][0]['password'], $aisyah->password))->toBeTrue();
});

test('baris invalid dilewati tetapi baris valid tetap dibuat', function () {
    $csv = "nama,email,password\n"
        .'Wali Valid,wali.valid@example.test,RahasiaKuat123'."\n"
        .",tanpa.nama@example.test,RahasiaKuat123\n"
        .'Wali Salah,bukan-email,RahasiaKuat123'."\n"
        .'Wali Pendek,wali.pendek@example.test,pendek'."\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), ['berkas' => berkasOrangTua($csv)]);

    expect(User::query()->where('role', Role::OrangTua)->count())->toBe(1)
        ->and(session('impor_orang_tua.dibuat'))->toBe(1)
        ->and(session('impor_orang_tua.dilewati'))->toBe(3)
        ->and(session('impor_orang_tua.galat'))->toHaveCount(3);
});

test('email kembar di dalam csv hanya dibuat sekali', function () {
    $csv = "nama,email,password\n"
        .'Wali Satu,wali@example.test,RahasiaKuat123'."\n"
        .'Wali Dua,wali@example.test,RahasiaKuat123'."\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), ['berkas' => berkasOrangTua($csv)]);

    expect(User::query()->where('email', 'wali@example.test')->count())->toBe(1)
        ->and(session('impor_orang_tua.dilewati'))->toBe(1);
});

test('csv tanpa kolom nama ditolak tanpa membuat akun', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("email,password\nwali@example.test,RahasiaKuat123\n"),
        ]);

    expect(User::query()->where('role', Role::OrangTua)->count())->toBe(0)
        ->and(session('impor_orang_tua.galat.0'))->toContain('nama');
});

test('csv bertitik koma dari excel indonesia ikut terbaca', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("nama;email;password\nWali Siti;wali.siti@example.test;RahasiaKuat123\n"),
        ]);

    expect(User::query()->where('email', 'wali.siti@example.test')->exists())->toBeTrue();
});

test('berkas selain csv ditolak dan guru tidak dapat mengimpor orang tua', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => UploadedFile::fake()->create('orang-tua.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('berkas');

    $this->actingAs(User::factory()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("nama\nTidak Boleh\n"),
        ])
        ->assertForbidden();

    expect(User::query()->where('name', 'Tidak Boleh')->exists())->toBeFalse();
});

test('email orang tua yang sudah terdaftar diperbarui tanpa mengganti password dan anaknya', function () {
    $wali = User::factory()->create(['role' => Role::OrangTua, 'email' => 'wali.siti@example.test', 'name' => 'Siti', 'is_active' => false]);
    $passwordLama = $wali->password;
    $siswa = Siswa::factory()->create();
    $siswa->orangTuas()->attach($wali->id);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("nama,email,password\nSiti Aminah,wali.siti@example.test,PasswordBaru123\n"),
        ])
        ->assertSessionHas('impor_orang_tua', fn (array $hasil): bool => $hasil['dibuat'] === 0 && $hasil['diperbarui'] === 1);

    $wali->refresh();

    expect(User::where('email', 'wali.siti@example.test')->count())->toBe(1)
        ->and($wali->name)->toBe('Siti Aminah')
        ->and($wali->is_active)->toBeTrue()
        ->and($wali->password)->toBe($passwordLama)
        ->and($wali->siswas()->pluck('siswas.id')->all())->toBe([$siswa->id]);
});

test('email milik guru tidak diubah jadi orang tua lewat impor', function () {
    $guru = User::factory()->create(['email' => 'guru@example.test', 'name' => 'Pak Guru']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orang-tua.impor'), [
            'berkas' => berkasOrangTua("nama,email\nBukan Wali,guru@example.test\n"),
        ])
        ->assertSessionHas('impor_orang_tua', fn (array $hasil): bool => $hasil['dilewati'] === 1);

    expect($guru->refresh()->name)->toBe('Pak Guru')
        ->and($guru->role)->toBe(Role::Guru);
});
