<?php

use App\Enums\Role;
use App\Enums\StatusPerangkat;
use App\Models\Kantor;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;

test('guru dilarang', fn () => $this->actingAs(User::factory()->create())->get(route('admin.guru.index'))->assertForbidden());
test('admin melihat guru', function () {
    $g = User::factory()->create();
    Perangkat::factory()->for($g)->create();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.guru.index'))->assertOk()->assertInertia(fn ($p) => $p->component('admin/Guru')->has('gurus', 1));
});
test('admin membuat guru', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.guru.store'), ['name' => 'A', 'nip' => '1', 'email' => 'a@test.test', 'password' => 'rahasia-panjang-sekali'])->assertRedirect();
    $g = User::whereEmail('a@test.test')->firstOrFail();
    expect($g->role)->toBe(Role::Guru)->and(Hash::check('rahasia-panjang-sekali', $g->password))->toBeTrue()->and($g->email_verified_at)->not->toBeNull();
});
test('email unik', function () {
    $g = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.guru.store'), ['name' => 'X', 'email' => $g->email, 'password' => 'rahasia-panjang-sekali'])->assertSessionHasErrors('email');
});
test('nonaktifkan guru', function () {
    $g = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create())->patch(route('admin.guru.update', $g), ['is_active' => false]);
    expect($g->refresh()->is_active)->toBeFalse();
});
test('approve mencabut lama', function () {
    $a = User::factory()->admin()->create();
    $g = User::factory()->create();
    $lama = Perangkat::factory()->for($g)->create();
    $baru = Perangkat::factory()->for($g)->pending()->create();
    $this->actingAs($a)->patch(route('admin.perangkat.update', $baru), ['status' => 'active']);
    expect($baru->refresh()->status)->toBe(StatusPerangkat::Active)->and($lama->refresh()->status)->toBe(StatusPerangkat::Revoked)->and($baru->approved_by)->toBe($a->id);
});
test('cabut perangkat', function () {
    $p = Perangkat::factory()->for(User::factory())->create();
    $this->actingAs(User::factory()->admin()->create())->patch(route('admin.perangkat.update', $p), ['status' => 'revoked']);
    expect($p->refresh()->status)->toBe(StatusPerangkat::Revoked);
});
test('guru tidak approve', function () {
    $g = User::factory()->create();
    $p = Perangkat::factory()->for($g)->pending()->create();
    $this->actingAs($g)->patch(route('admin.perangkat.update', $p), ['status' => 'active'])->assertForbidden();
});

test('halaman guru memakai modal, tabel, filter cari, dan tombol next', function () {
    $halaman = file_get_contents(resource_path('js/pages/admin/Guru.svelte'));

    expect($halaman)
        // Tambah guru dan impor pindah ke modal, halamannya tinggal daftar.
        ->toContain('<Dialog bind:open={dialogTambah}>')
        ->toContain('<Dialog bind:open={dialogImpor}>')
        // Daftar guru berupa tabel dengan pencarian langsung di klien.
        ->toContain('<table')
        ->toContain('placeholder="Cari nama, NIP, email"')
        // Pilihan jumlah baris, 0 berarti semua.
        ->toContain('<option value={10}>')
        ->toContain('<option value={15}>')
        ->toContain('<option value={20}>')
        ->toContain('<option value={0}>Semua</option>')
        // Pagination hanya maju.
        ->toContain('tampil += perHalaman')
        ->not->toContain('Sebelumnya');
});

test('aksi akun di kelola guru memakai endpoint kelola user', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create(['name' => 'Sitti']);

    // Ubah identitas.
    $this->actingAs($admin)
        ->from(route('admin.guru.index'))
        ->patch(route('admin.user.update', $guru), ['name' => 'Siti Aminah'])
        ->assertRedirect(route('admin.guru.index'));

    expect($guru->refresh()->name)->toBe('Siti Aminah');

    // Reset password, kembali ke halaman guru beserta passwordnya.
    $this->actingAs($admin)
        ->from(route('admin.guru.index'))
        ->post(route('admin.user.reset-password', $guru))
        ->assertRedirect(route('admin.guru.index'))
        ->assertSessionHas('password_baru');

    // Hapus akun.
    $this->actingAs($admin)
        ->from(route('admin.guru.index'))
        ->delete(route('admin.user.destroy', $guru))
        ->assertRedirect(route('admin.guru.index'));

    expect(User::whereKey($guru->id)->exists())->toBeFalse();
});

test('halaman guru menerima password baru untuk ditampilkan sekali', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.guru.index'))
        ->post(route('admin.user.reset-password', $guru));

    $this->actingAs($admin)
        ->get(route('admin.guru.index'))
        ->assertInertia(fn ($p) => $p->has('passwordBaru.password'));
});

test('guru baru bisa langsung diberi unit', function () {
    $kantor = Kantor::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.store'), [
            'name' => 'Bu Siti',
            'email' => 'siti@sekolah.test',
            'password' => 'RahasiaKuat123!',
            'kantor_id' => $kantor->id,
        ])->assertSessionHasNoErrors();

    expect(User::where('email', 'siti@sekolah.test')->value('kantor_id'))->toBe($kantor->id);
});

test('menyetujui dan menonaktifkan memberi pesan sukses', function () {
    $guru = User::factory()->create();
    $perangkat = Perangkat::factory()->for($guru)->pending()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.perangkat.update', $perangkat), ['status' => 'active']);
    expect(session(SessionKey::FLASH_DATA)['toast']['message'])->toContain('disetujui');

    $this->actingAs($admin)->patch(route('admin.guru.update', $guru), ['is_active' => false]);
    expect(session(SessionKey::FLASH_DATA)['toast']['message'])->toBe($guru->name.' dinonaktifkan.');
});
