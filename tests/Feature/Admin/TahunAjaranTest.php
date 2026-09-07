<?php

use App\Enums\Role;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Pengumuman;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 07:30:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka tahun ajaran', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.tahun-ajaran.index'))->assertForbidden();
});

test('seeder membuat satu tahun ajaran aktif', function () {
    expect(TahunAjaran::where('is_active', true)->count())->toBe(1)
        ->and(TahunAjaran::where('is_active', true)->value('nama'))->toBe('2026/2027');
});

test('data baru distempel tahun ajaran aktif', function () {
    $aktif = TahunAjaran::aktif();
    $libur = HariLibur::factory()->create();

    expect($libur->tahun_ajaran_id)->toBe($aktif->id);
});

test('mengaktifkan tahun baru menyisakan layar bersih tanpa menghapus data lama', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();
    HariLibur::factory()->create();
    Pengumuman::factory()->create();

    $lama = TahunAjaran::aktif();
    $baru = TahunAjaran::factory()->create(['nama' => '2027/2028']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tahun-ajaran.aktifkan', $baru))
        ->assertRedirect(route('admin.tahun-ajaran.index'));

    // Tahun baru: layar bersih.
    app()->forgetInstance('tahun_ajaran_id');
    expect(Absensi::count())->toBe(0)
        ->and(HariLibur::count())->toBe(0)
        ->and(Pengumuman::count())->toBe(0)
        ->and(JadwalKerja::count())->toBe(0)
        // Tapi barisnya masih ada, hanya milik tahun lain.
        ->and(Absensi::withoutGlobalScopes()->where('tahun_ajaran_id', $lama->id)->count())->toBe(1)
        ->and(HariLibur::withoutGlobalScopes()->where('tahun_ajaran_id', $lama->id)->count())->toBe(1)
        ->and(JadwalKerja::withoutGlobalScopes()->where('tahun_ajaran_id', $lama->id)->count())->toBe(7)
        // Guru dan rolenya tidak tersentuh.
        ->and(User::whereKey($guru->id)->exists())->toBeTrue()
        ->and($guru->refresh()->role)->toBe(Role::Guru);
});

test('hanya satu tahun ajaran boleh aktif', function () {
    $baru = TahunAjaran::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tahun-ajaran.aktifkan', $baru));

    expect(TahunAjaran::where('is_active', true)->count())->toBe(1)
        ->and(TahunAjaran::where('is_active', true)->value('id'))->toBe($baru->id);
});

test('admin bisa menengok tahun lain tanpa mengubah yang aktif', function () {
    $aktif = TahunAjaran::aktif();
    $lain = TahunAjaran::factory()->create(['nama' => '2025/2026']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.tahun-ajaran.lihat', $lain))
        ->assertRedirect(route('admin.tahun-ajaran.index'))
        ->assertSessionHas('tahun_ajaran_id', $lain->id);

    expect(TahunAjaran::where('is_active', true)->value('id'))->toBe($aktif->id);
});

test('admin menambah dan mengubah tahun ajaran', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2028/2029',
        'tanggal_mulai' => '2028-07-01',
        'tanggal_selesai' => '2029-06-30',
    ])->assertRedirect();

    $tahun = TahunAjaran::where('nama', '2028/2029')->firstOrFail();
    expect($tahun->is_active)->toBeFalse();

    $this->actingAs($admin)->put(route('admin.tahun-ajaran.update', $tahun), [
        'nama' => '2028/2029',
        'tanggal_mulai' => '2028-07-15',
        'tanggal_selesai' => '2029-06-30',
    ])->assertRedirect();

    expect($tahun->refresh()->tanggal_mulai->toDateString())->toBe('2028-07-15');
});

test('nama tahun ajaran tidak boleh kembar dan tanggal selesai harus setelah mulai', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
    ])->assertSessionHasErrors('nama');

    $this->actingAs($admin)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2030/2031',
        'tanggal_mulai' => '2030-07-01',
        'tanggal_selesai' => '2030-06-30',
    ])->assertSessionHasErrors('tanggal_selesai');
});

test('guru tidak bisa mengaktifkan tahun ajaran', function () {
    $baru = TahunAjaran::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.tahun-ajaran.aktifkan', $baru))
        ->assertForbidden();

    expect($baru->refresh()->is_active)->toBeFalse();
});

test('seeder menstempel tahun ajaran walau event model dimatikan', function () {
    // DatabaseSeeder memakai WithoutModelEvents; stempel otomatis lewat hook
    // creating tidak jalan di sana, jadi seedernya harus mengisi sendiri.
    JadwalKerja::query()->withoutGlobalScopes()->delete();

    Model::withoutEvents(function (): void {
        app(JadwalKerjaSeeder::class)->run();
    });

    expect(JadwalKerja::query()->withoutGlobalScopes()->whereNull('tahun_ajaran_id')->count())->toBe(0)
        ->and(JadwalKerja::count())->toBe(7);
});
