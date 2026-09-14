<?php

use App\Enums\Role;
use App\Models\AnggotaKelas;
use App\Models\GuruKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-14 07:00:00');
    tahunAjaranAktif('2026/2027');
});

test('guru dan orang tua ditolak dari kelola kelas', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kelas.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::OrangTua]))->get(route('admin.kelas.index'))->assertForbidden();
});

test('admin membuat kelas dengan wali', function () {
    $kantor = Kantor::factory()->create();
    $wali = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.kelas.store'), [
            'kantor_id' => $kantor->id,
            'nama' => '5A',
            'tingkat' => 5,
            'wali_kelas_id' => $wali->id,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', '5A')->firstOrFail();

    expect($kelas->wali_kelas_id)->toBe($wali->id)
        ->and($kelas->tahun_ajaran_id)->toBe(TahunAjaran::aktif()->id);
});

test('nama kelas kembar ditolak validasi', function () {
    $kantor = Kantor::factory()->create();
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.kelas.store'), [
            'kantor_id' => $kantor->id,
            'nama' => '5A',
            'tingkat' => 5,
            'wali_kelas_id' => null,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('nama');
});

test('wali kelas harus guru atau admin, bukan orang tua', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.kelas.store'), [
            'kantor_id' => Kantor::factory()->create()->id,
            'nama' => '6A',
            'tingkat' => 6,
            'wali_kelas_id' => User::factory()->create(['role' => Role::OrangTua])->id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('wali_kelas_id');
});

test('admin memasukkan siswa ke kelas', function () {
    $kantor = Kantor::factory()->create();
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id]);
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.kelas.anggota.store', $kelas), [
            'siswa_ids' => [$siswa->id],
            'tanggal_mulai' => '2026-07-15',
        ])
        ->assertRedirect();

    expect(AnggotaKelas::where('kelas_id', $kelas->id)->where('siswa_id', $siswa->id)->where('is_active', true)->exists())
        ->toBeTrue();
});

test('admin dapat memasukkan beberapa siswa sekaligus melalui checklist', function () {
    $kantor = Kantor::factory()->create();
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id]);
    $siswas = Siswa::factory()->count(3)->create(['kantor_id' => $kantor->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.kelas.anggota.store', $kelas), [
            'siswa_ids' => $siswas->pluck('id')->all(),
            'tanggal_mulai' => '2026-09-14',
        ])
        ->assertSessionHasNoErrors();

    expect(AnggotaKelas::where('kelas_id', $kelas->id)->count())->toBe(3);
});

test('siswa yang sudah aktif di kelas lain tidak muncul sebagai calon anggota', function () {
    $kantor = Kantor::factory()->create();
    $kelasLama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '4A']);
    $kelasBaru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $sudahBerkelas = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $belumBerkelas = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    AnggotaKelas::factory()->create([
        'kelas_id' => $kelasLama->id,
        'siswa_id' => $sudahBerkelas->id,
        'tanggal_mulai' => '2026-07-01',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.kelas.index', ['kelas' => $kelasBaru->id]))
        ->assertInertia(fn ($page) => $page
            ->where('terpilih.calonSiswas.0.id', $belumBerkelas->id)
            ->missing('terpilih.calonSiswas.1'));
});

test('siswa dari kantor lain tidak boleh dimasukkan ke kelas ini', function () {
    $kelas = Kelas::factory()->create(['kantor_id' => Kantor::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['kantor_id' => Kantor::factory()->create()->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.kelas.anggota.store', $kelas), [
            'siswa_ids' => [$siswa->id],
            'tanggal_mulai' => '2026-07-15',
        ])
        ->assertSessionHasErrors('siswa_ids.0');
});

test('memindahkan siswa lewat layar admin menutup keanggotaan lama', function () {
    $kantor = Kantor::factory()->create();
    $lama = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    $baru = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5B']);
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.kelas.anggota.store', $lama), [
        'siswa_ids' => [$siswa->id], 'tanggal_mulai' => '2026-07-15',
    ]);
    $this->actingAs($admin)->post(route('admin.kelas.anggota.store', $baru), [
        'siswa_ids' => [$siswa->id], 'tanggal_mulai' => '2026-10-01',
    ]);

    expect(AnggotaKelas::where('siswa_id', $siswa->id)->count())->toBe(2)
        ->and(AnggotaKelas::where('siswa_id', $siswa->id)->where('is_active', true)->value('kelas_id'))->toBe($baru->id);
});

test('mengeluarkan anggota menutup keanggotaannya tanpa menghapus barisnya', function () {
    $kantor = Kantor::factory()->create();
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id]);
    $anggota = AnggotaKelas::factory()->create([
        'kelas_id' => $kelas->id,
        'siswa_id' => Siswa::factory()->create(['kantor_id' => $kantor->id])->id,
        'tanggal_mulai' => '2026-07-15',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.kelas.anggota.destroy', [$kelas, $anggota]))
        ->assertRedirect();

    expect($anggota->refresh()->is_active)->toBeFalse()
        ->and($anggota->tanggal_selesai)->not->toBeNull()
        ->and(AnggotaKelas::whereKey($anggota->id)->exists())->toBeTrue();
});

test('admin menugaskan dan mencabut guru pengganti', function () {
    $kelas = Kelas::factory()->create();
    $pengganti = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.kelas.pengganti.store', $kelas), [
        'user_id' => $pengganti->id,
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-20',
    ])->assertRedirect();

    $penugasan = GuruKelas::where('kelas_id', $kelas->id)->firstOrFail();

    expect(Kelas::diampuOleh($pengganti, Carbon::parse('2026-09-14'))->count())->toBe(1);

    $this->actingAs($admin)->delete(route('admin.kelas.pengganti.destroy', [$kelas, $penugasan]));

    expect(GuruKelas::count())->toBe(0);
});

test('kelas yang masih punya anggota tidak bisa dihapus', function () {
    $kantor = Kantor::factory()->create();
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id]);
    AnggotaKelas::factory()->create([
        'kelas_id' => $kelas->id,
        'siswa_id' => Siswa::factory()->create(['kantor_id' => $kantor->id])->id,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.kelas.destroy', $kelas))
        ->assertRedirect();

    expect(Kelas::whereKey($kelas->id)->exists())->toBeTrue();
});
