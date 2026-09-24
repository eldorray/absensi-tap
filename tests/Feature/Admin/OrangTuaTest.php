<?php

use App\Enums\Role;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => tahunAjaranAktif('2026/2027'));

test('halaman akun staf dan orang tua dipisahkan', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Sekolah']);
    $guru = User::factory()->create(['name' => 'Guru Kelas']);
    $orangTua = User::factory()->orangTua()->create(['name' => 'Wali Murid']);
    $siswa = Siswa::factory()->create(['nama' => 'Aisyah']);
    $orangTua->siswas()->attach($siswa);

    $this->actingAs($admin)->get(route('admin.user.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/User')
            ->has('users', 2)
            ->has('roles', 2)
            ->where('users.0.name', 'Admin Sekolah')
            ->where('users.1.name', 'Guru Kelas'));

    $this->actingAs($admin)->get(route('admin.orang-tua.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/OrangTua')
            ->has('orangTuas', 1)
            ->where('orangTuas.0.name', 'Wali Murid')
            ->where('orangTuas.0.anak.0.nama', 'Aisyah'));
});

test('admin membuat akun orang tua dari endpoint khusus', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.orang-tua.store'), [
        'name' => 'Wali Aisyah',
        'email' => 'wali.aisyah@example.test',
        'password' => 'RahasiaKuat123',
    ])->assertRedirect(route('admin.orang-tua.index'));

    $orangTua = User::query()->where('email', 'wali.aisyah@example.test')->firstOrFail();

    expect($orangTua->role)->toBe(Role::OrangTua)
        ->and($orangTua->nip)->toBeNull()
        ->and($orangTua->kantor_id)->toBeNull()
        ->and($orangTua->is_active)->toBeTrue()
        ->and($orangTua->email_verified_at)->not->toBeNull();
});

test('endpoint akun staf tidak menerima role orang tua', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.user.store'), [
        'name' => 'Wali Salah Jalur',
        'email' => 'salah.jalur@example.test',
        'password' => 'RahasiaKuat123',
        'role' => Role::OrangTua->value,
    ])->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'salah.jalur@example.test')->exists())->toBeFalse();
});

test('admin mengubah status dan identitas orang tua dari endpoint khusus', function () {
    $admin = User::factory()->admin()->create();
    $orangTua = User::factory()->orangTua()->create();

    $this->actingAs($admin)->patch(route('admin.orang-tua.update', $orangTua), [
        'name' => 'Nama Wali Baru',
        'email' => 'wali.baru@example.test',
        'is_active' => false,
    ])->assertRedirect();

    $orangTua->refresh();

    expect($orangTua->name)->toBe('Nama Wali Baru')
        ->and($orangTua->email)->toBe('wali.baru@example.test')
        ->and($orangTua->is_active)->toBeFalse()
        ->and($orangTua->role)->toBe(Role::OrangTua);
});

test('admin dapat mereset password dan menghapus akun orang tua tanpa menghapus siswa', function () {
    $admin = User::factory()->admin()->create();
    $orangTua = User::factory()->orangTua()->create();
    $siswa = Siswa::factory()->create();
    $orangTua->siswas()->attach($siswa);
    $passwordLama = $orangTua->password;

    $this->actingAs($admin)
        ->from(route('admin.orang-tua.index'))
        ->post(route('admin.orang-tua.reset-password', $orangTua))
        ->assertRedirect(route('admin.orang-tua.index'))
        ->assertSessionHas('password_baru');

    $passwordBaru = session('password_baru');

    expect($orangTua->refresh()->password)->not->toBe($passwordLama)
        ->and(Hash::check($passwordBaru['password'], $orangTua->password))->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.orang-tua.destroy', $orangTua))
        ->assertRedirect();

    expect(User::query()->whereKey($orangTua->id)->exists())->toBeFalse()
        ->and(Siswa::query()->whereKey($siswa->id)->exists())->toBeTrue();
});

test('endpoint khusus orang tua menolak akun staf dan pengguna non admin', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create();
    $orangTua = User::factory()->orangTua()->create();

    $this->actingAs($admin)
        ->patch(route('admin.orang-tua.update', $guru), ['name' => 'Tidak Boleh'])
        ->assertNotFound();

    $this->actingAs($guru)->get(route('admin.orang-tua.index'))->assertForbidden();
    $this->actingAs($orangTua)->get(route('admin.orang-tua.index'))->assertForbidden();
});

test('sidebar menyediakan menu orang tua terpisah dari akun staf', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));

    expect($sidebar)
        ->toContain("title: 'Akun staf'")
        ->toContain("title: 'Orang tua'")
        ->toContain('@/routes/admin/orang-tua');
});

test('halaman orang tua menyediakan aksi upload csv', function () {
    $halaman = file_get_contents(resource_path('js/pages/admin/OrangTua.svelte'));

    expect($halaman)
        ->toContain('Upload CSV')
        ->toContain('Template CSV')
        ->toContain('accept=".csv,text/csv"')
        ->toContain('orangTuaImpor()');
});
