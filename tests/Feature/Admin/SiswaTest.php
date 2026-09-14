<?php

use App\Enums\Role;
use App\Models\AnggotaKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
});

test('guru tidak boleh membuka kelola siswa', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.siswa.index'))
        ->assertForbidden();
});

test('orang tua tidak boleh membuka kelola siswa', function () {
    $this->actingAs(User::factory()->create(['role' => Role::OrangTua]))
        ->get(route('admin.siswa.index'))
        ->assertForbidden();
});

test('admin menambah siswa', function () {
    $kantor = Kantor::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.store'), [
            'kantor_id' => $kantor->id,
            'nis' => '20260001',
            'nisn' => '0091234567',
            'nama' => 'Aisyah Putri',
            'jenis_kelamin' => 'P',
            'tanggal_lahir' => '2015-04-11',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.siswa.index'));

    expect(Siswa::where('nis', '20260001')->value('nama'))->toBe('Aisyah Putri');
});

test('nis kembar di kantor yang sama ditolak validasi, bukan galat database', function () {
    $kantor = Kantor::factory()->create();
    Siswa::factory()->create(['kantor_id' => $kantor->id, 'nis' => '20260001']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.siswa.store'), [
            'kantor_id' => $kantor->id,
            'nis' => '20260001',
            'nama' => 'Nama Lain',
            'jenis_kelamin' => 'L',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('nis');
});

test('admin mengubah siswa', function () {
    $siswa = Siswa::factory()->create(['nama' => 'Nama Lama']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.siswa.update', $siswa), [
            'kantor_id' => $siswa->kantor_id,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
            'nama' => 'Nama Baru',
            'jenis_kelamin' => $siswa->jenis_kelamin->value,
            'tanggal_lahir' => null,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($siswa->refresh()->nama)->toBe('Nama Baru');
});

test('siswa yang punya riwayat kelas dinonaktifkan, bukan dihapus', function () {
    $siswa = Siswa::factory()->create();
    AnggotaKelas::factory()->create([
        'siswa_id' => $siswa->id,
        'kelas_id' => Kelas::factory()->create(['kantor_id' => $siswa->kantor_id])->id,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.siswa.destroy', $siswa))
        ->assertRedirect();

    expect(Siswa::whereKey($siswa->id)->exists())->toBeTrue()
        ->and($siswa->refresh()->is_active)->toBeFalse();
});

test('unit siswa dengan riwayat kelas tidak boleh diubah', function () {
    $siswa = Siswa::factory()->create();
    AnggotaKelas::factory()->create([
        'siswa_id' => $siswa->id,
        'kelas_id' => Kelas::factory()->create(['kantor_id' => $siswa->kantor_id])->id,
    ]);
    $kantorAwal = $siswa->kantor_id;

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.siswa.update', $siswa), [
            'kantor_id' => Kantor::factory()->create()->id,
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'jenis_kelamin' => $siswa->jenis_kelamin->value,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('kantor_id');

    expect($siswa->refresh()->kantor_id)->toBe($kantorAwal);
});

test('siswa tanpa riwayat boleh dihapus', function () {
    $siswa = Siswa::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.siswa.destroy', $siswa));

    expect(Siswa::whereKey($siswa->id)->exists())->toBeFalse();
});

test('daftar siswa dipaginasi dan bisa dicari', function () {
    $kantor = Kantor::factory()->create();
    Siswa::factory()->count(30)->create(['kantor_id' => $kantor->id]);
    Siswa::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'Zulfikar Rahman']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.siswa.index', ['cari' => 'Zulfikar']))
        ->assertInertia(fn ($p) => $p->has('siswas.data', 1)
            ->where('siswas.data.0.nama', 'Zulfikar Rahman'));
});
