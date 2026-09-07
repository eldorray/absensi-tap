<?php

use App\Enums\Role;
use App\Models\Kantor;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guru tidak boleh membuka kelola user maupun kelola role', function () {
    $guru = User::factory()->create();

    $this->actingAs($guru)->get(route('admin.user.index'))->assertForbidden();
    $this->actingAs($guru)->get(route('admin.role.index'))->assertForbidden();
});

test('admin melihat seluruh akun beserta role dan kantornya', function () {
    $kantor = Kantor::factory()->create(['nama' => 'MI Syekh Yusuf']);
    User::factory()->create(['name' => 'Bu Ani', 'kantor_id' => $kantor->id]);

    $this->actingAs(User::factory()->admin()->create(['name' => 'Zulkifli']))
        ->get(route('admin.user.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/User')
            ->has('users', 2)
            ->has('kantors', 1)
            ->has('roles', 2)
            ->where('users.0.name', 'Bu Ani')
            ->where('users.0.kantor', 'MI Syekh Yusuf'));
});

test('admin membuat akun dengan role dan kantor', function () {
    $kantor = Kantor::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.user.store'), [
            'name' => 'Pak TU',
            'nip' => '123',
            'email' => 'tu@sekolah.sch.id',
            'password' => 'RahasiaKuat123',
            'role' => 'admin',
            'kantor_id' => $kantor->id,
        ])
        ->assertRedirect(route('admin.user.index'));

    $user = User::where('email', 'tu@sekolah.sch.id')->firstOrFail();

    expect($user->role)->toBe(Role::Admin)
        ->and($user->kantor_id)->toBe($kantor->id)
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('admin mengubah role, kantor, dan status akun guru', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create();
    $kantor = Kantor::factory()->create();

    $this->actingAs($admin)->patch(route('admin.user.update', $guru), ['role' => 'admin'])->assertRedirect();
    expect($guru->refresh()->role)->toBe(Role::Admin);

    $this->actingAs($admin)->patch(route('admin.user.update', $guru), ['kantor_id' => $kantor->id])->assertRedirect();
    expect($guru->refresh()->kantor_id)->toBe($kantor->id);

    $this->actingAs($admin)->patch(route('admin.user.update', $guru), ['is_active' => false])->assertRedirect();
    expect($guru->refresh()->is_active)->toBeFalse();
});

test('akun sendiri tidak bisa menurunkan rolenya', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.user.update', $admin), ['role' => 'guru'])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->role)->toBe(Role::Admin);
});

test('admin aktif terakhir tidak bisa dicabut', function () {
    $admin = User::factory()->admin()->create();
    $lain = User::factory()->admin()->create();

    // Turunkan admin lain dulu, sisa satu admin aktif.
    $this->actingAs($admin)->patch(route('admin.user.update', $lain), ['role' => 'guru'])->assertRedirect();

    $this->actingAs($lain->refresh())->get(route('admin.user.index'))->assertForbidden();

    // Sekarang $admin satu-satunya. Nonaktifkan lewat admin itu sendiri ditolak.
    $this->actingAs($admin)
        ->patch(route('admin.user.update', $admin), ['is_active' => false])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->is_active)->toBeTrue();
});

test('halaman kelola role menampilkan akses dan pemegangnya', function () {
    User::factory()->admin()->create(['name' => 'Pak Admin']);
    User::factory()->create(['name' => 'Bu Ani']);

    $this->actingAs(User::where('role', Role::Admin)->firstOrFail())
        ->get(route('admin.role.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/Role')
            ->has('roles', 2)
            ->where('roles.0.value', 'guru')
            ->has('roles.0.akses')
            ->has('roles.0.pemegang'));
});

test('guru nonaktif tidak bisa dipakai absen maupun masuk menu admin', function () {
    $guru = User::factory()->create(['is_active' => false]);

    $this->actingAs($guru)->get(route('admin.user.index'))->assertForbidden();
});

test('admin mengubah nama, nip, dan email akun', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create(['name' => 'Sitti', 'email' => 'lama@sekolah.sch.id']);

    $this->actingAs($admin)
        ->from(route('admin.user.index'))
        ->patch(route('admin.user.update', $guru), [
            'name' => 'Siti Aminah',
            'nip' => '198501012010012001',
            'email' => 'siti@sekolah.sch.id',
        ])
        ->assertRedirect(route('admin.user.index'));

    $guru->refresh();

    expect($guru->name)->toBe('Siti Aminah')
        ->and($guru->nip)->toBe('198501012010012001')
        ->and($guru->email)->toBe('siti@sekolah.sch.id');
});

test('email tidak boleh bentrok dengan akun lain saat diubah', function () {
    $admin = User::factory()->admin()->create();
    $lain = User::factory()->create();
    $guru = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.user.update', $guru), ['email' => $lain->email])
        ->assertSessionHasErrors('email');
});

test('admin mereset password dan melihatnya sekali', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create();
    $lama = $guru->password;

    // Dikembalikan ke halaman asal: tombolnya dipakai di /admin/user dan /admin/guru.
    $this->actingAs($admin)
        ->from(route('admin.guru.index'))
        ->post(route('admin.user.reset-password', $guru))
        ->assertRedirect(route('admin.guru.index'))
        ->assertSessionHas('password_baru');

    $baru = session('password_baru');

    expect($guru->refresh()->password)->not->toBe($lama)
        ->and($baru['password'])->toHaveLength(12)
        ->and(Hash::check($baru['password'], $guru->password))->toBeTrue()
        ->and($baru['email'])->toBe($guru->email);
});

test('guru tidak bisa mereset password akun lain', function () {
    $guru = User::factory()->create();
    $lain = User::factory()->create();

    $this->actingAs($guru)
        ->post(route('admin.user.reset-password', $lain))
        ->assertForbidden();
});

test('admin menghapus akun beserta jejaknya', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create();
    Perangkat::factory()->for($guru)->create();

    $this->actingAs($admin)
        ->from(route('admin.user.index'))
        ->delete(route('admin.user.destroy', $guru))
        ->assertRedirect(route('admin.user.index'));

    expect(User::whereKey($guru->id)->exists())->toBeFalse()
        ->and(Perangkat::where('user_id', $guru->id)->count())->toBe(0);
});

test('akun sendiri dan admin aktif terakhir tidak bisa dihapus', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.user.destroy', $admin))
        ->assertSessionHasErrors('user');

    $lain = User::factory()->admin()->create();

    // Nonaktifkan admin lain, jadi $lain bukan lagi penjaga akses terakhir.
    $this->actingAs($admin)->patch(route('admin.user.update', $lain), ['is_active' => false]);

    $this->actingAs($lain->refresh())->get(route('admin.user.index'))->assertForbidden();

    expect(User::whereKey($admin->id)->exists())->toBeTrue();
});

test('tombol aksi kelola user dan kelola guru berupa ikon yang sama', function () {
    foreach ([
        ['admin/User.svelte', 'u'],
        ['admin/Guru.svelte', 'g'],
    ] as [$berkas, $variabel]) {
        $halaman = file_get_contents(resource_path('js/pages/'.$berkas));

        expect($halaman)
            ->toContain('ikon={Pencil}')
            ->toContain('ikon={KeyRound}')
            ->toContain('ikon={Power}')
            ->toContain('ikon={Trash2}')
            ->toContain('nada="merah"')
            ->toContain('label={`Hapus akun ${'.$variabel.'.name}`}');
    }

    // Ikon tanpa teks tetap butuh nama yang terbaca pembaca layar, dan
    // warnanya baru muncul saat hover supaya tabelnya tidak ramai.
    expect(file_get_contents(resource_path('js/components/TombolIkon.svelte')))
        ->toContain('title={label}')
        ->toContain('aria-label={label}')
        ->toContain('hover:bg-destructive/10 hover:text-destructive');
});
