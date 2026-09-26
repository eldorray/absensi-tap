<?php

use App\Enums\StatusIzin;
use App\Models\Izin;
use App\Models\IzinOrangTua;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
});

test('admin melihat pengajuan orang tua secara terpisah dari izin guru', function () {
    $orangTua = User::factory()->orangTua()->create(['name' => 'Wali Aisyah']);
    $anak = Siswa::factory()->create(['nama' => 'Aisyah Putri']);
    $izin = IzinOrangTua::factory()->create([
        'user_id' => $orangTua->id,
        'siswa_id' => $anak->id,
        'nama_pengaju' => $orangTua->name,
        'email_pengaju' => $orangTua->email,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.izin-orang-tua.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IzinOrangTua')
            ->has('izins', 1)
            ->where('izins.0.id', $izin->id)
            ->where('izins.0.orang_tua', 'Wali Aisyah')
            ->where('izins.0.siswa', 'Aisyah Putri'));
});

test('daftar izin orang tua ditampilkan sebagai tabel', function () {
    $halaman = file_get_contents(resource_path('js/pages/admin/IzinOrangTua.svelte'));

    expect($halaman)
        ->toContain('<table')
        ->toContain('Siswa')
        ->toContain('Orang tua')
        ->toContain('Pengajuan')
        ->toContain('Alasan')
        ->toContain('Status')
        ->toContain('Review')
        ->not->toContain('<article class="g-tile');
});

test('admin menyetujui pengajuan orang tua', function () {
    $admin = User::factory()->admin()->create();
    $izin = IzinOrangTua::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.izin-orang-tua.update', $izin), [
            'status' => 'disetujui',
            'catatan_review' => 'Pengajuan diterima.',
        ])
        ->assertRedirect(route('admin.izin-orang-tua.index'));

    expect($izin->refresh()->status)->toBe(StatusIzin::Disetujui)
        ->and($izin->reviewed_by)->toBe($admin->id)
        ->and($izin->catatan_review)->toBe('Pengajuan diterima.');
});

test('izin orang tua tidak tercampur dengan izin guru', function () {
    Izin::factory()->count(2)->create();
    IzinOrangTua::factory()->count(3)->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.izin.index'))
        ->assertInertia(fn ($page) => $page->has('izins', 2));

    $this->actingAs($admin)->get(route('admin.izin-orang-tua.index'))
        ->assertInertia(fn ($page) => $page->has('izins', 3));
});

test('orang tua dan guru tidak bisa membuka review izin orang tua', function () {
    $izin = IzinOrangTua::factory()->create();

    foreach ([User::factory()->orangTua()->create(), User::factory()->create()] as $user) {
        $this->actingAs($user)->get(route('admin.izin-orang-tua.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.izin-orang-tua.update', $izin), [
            'status' => 'disetujui',
        ])->assertForbidden();
    }
});

test('pengajuan yang sudah direview tidak dapat diubah lagi', function () {
    $adminPertama = User::factory()->admin()->create();
    $adminKedua = User::factory()->admin()->create();
    $izin = IzinOrangTua::factory()->create();

    $this->actingAs($adminPertama)
        ->patch(route('admin.izin-orang-tua.update', $izin), [
            'status' => 'disetujui',
            'catatan_review' => 'Disetujui pertama kali.',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($adminKedua)
        ->patch(route('admin.izin-orang-tua.update', $izin), [
            'status' => 'ditolak',
            'catatan_review' => 'Dicoba diubah.',
        ])
        ->assertSessionHasErrors('status');

    expect($izin->refresh()->status)->toBe(StatusIzin::Disetujui)
        ->and($izin->reviewed_by)->toBe($adminPertama->id)
        ->and($izin->catatan_review)->toBe('Disetujui pertama kali.');
});

test('status pending tidak diterima sebagai hasil review izin orang tua', function () {
    $izin = IzinOrangTua::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.izin-orang-tua.update', $izin), ['status' => 'pending'])
        ->assertSessionHasErrors('status');

    expect($izin->refresh()->status)->toBe(StatusIzin::Pending);
});

test('admin dapat mengunduh lampiran izin orang tua dari route admin', function () {
    Storage::fake('local');
    $izin = IzinOrangTua::factory()->create([
        'lampiran_path' => 'izin-orang-tua/surat.pdf',
    ]);
    Storage::disk('local')->put('izin-orang-tua/surat.pdf', 'isi');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.izin-orang-tua.lampiran', $izin))
        ->assertOk();
});

test('riwayat izin tetap tersimpan ketika akun orang tua dihapus', function () {
    $orangTua = User::factory()->orangTua()->create();
    $izin = IzinOrangTua::factory()->create(['user_id' => $orangTua->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.orang-tua.destroy', $orangTua))
        ->assertRedirect();

    expect(User::query()->whereKey($orangTua->id)->exists())->toBeFalse()
        ->and($izin->refresh()->user_id)->toBeNull();
});

test('sidebar dan halaman admin menyediakan review izin orang tua terpisah', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.svelte'));
    $halaman = file_get_contents(resource_path('js/pages/admin/IzinOrangTua.svelte'));

    expect($sidebar)
        ->toContain("title: 'Izin orang tua'")
        ->toContain('@/routes/admin/izin-orang-tua')
        ->and($halaman)
        ->toContain('Pengajuan izin orang tua')
        ->toContain('Setujui')
        ->toContain('Tolak');
});
