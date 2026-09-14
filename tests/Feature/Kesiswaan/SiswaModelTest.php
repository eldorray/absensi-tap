<?php

use App\Enums\JenisKelamin;
use App\Models\Kantor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\QueryException;

test('nis boleh sama di kantor berbeda', function () {
    $mi = Kantor::factory()->create(['nama' => 'MI']);
    $smp = Kantor::factory()->create(['nama' => 'SMP']);

    Siswa::factory()->create(['kantor_id' => $mi->id, 'nis' => '001']);
    Siswa::factory()->create(['kantor_id' => $smp->id, 'nis' => '001']);

    expect(Siswa::count())->toBe(2);
});

test('nis tidak boleh kembar di kantor yang sama', function () {
    $kantor = Kantor::factory()->create();
    Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '001']);

    Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '001']);
})->throws(QueryException::class);

test('nisn unik kalau terisi tapi boleh kosong berkali-kali', function () {
    Siswa::factory()->count(2)->create(['nisn' => null]);

    expect(Siswa::whereNull('nisn')->count())->toBe(2);

    Siswa::factory()->create(['nisn' => '9990001']);

    expect(fn () => Siswa::factory()->create(['nisn' => '9990001']))
        ->toThrow(QueryException::class);
});

test('jenis kelamin dicast ke enum', function () {
    $siswa = Siswa::factory()->create(['jenis_kelamin' => JenisKelamin::Perempuan]);

    expect($siswa->refresh()->jenis_kelamin)->toBe(JenisKelamin::Perempuan)
        ->and($siswa->jenis_kelamin->label())->toBe('Perempuan');
});

test('siswa tidak ikut tersaring saat admin menengok tahun ajaran lain', function () {
    $siswa = Siswa::factory()->create();
    $tahunLain = TahunAjaran::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tahun-ajaran.lihat', $tahunLain));

    expect(Siswa::whereKey($siswa->id)->exists())->toBeTrue();
});

test('kantor yang masih punya siswa tidak bisa dihapus', function () {
    $kantor = Kantor::factory()->create();
    Siswa::factory()->create(['kantor_id' => $kantor->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.kantor.destroy', $kantor))
        ->assertRedirect();

    expect(Kantor::whereKey($kantor->id)->exists())->toBeTrue();
});
