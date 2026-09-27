<?php

use App\Models\IzinOrangTua;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
    Carbon::setTestNow('2026-09-16 08:00:00');
});

afterEach(fn () => Carbon::setTestNow());

test('orang tua mengajukan izin untuk anak yang tertaut', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);

    $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anak->id,
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-18',
        'alasan' => 'Demam dan perlu beristirahat.',
    ])->assertRedirect(route('orang-tua.izin.index'));

    $this->assertDatabaseHas('izin_orang_tuas', [
        'user_id' => $orangTua->id,
        'siswa_id' => $anak->id,
        'tipe' => 'sakit',
        'status' => 'pending',
    ]);
});

test('orang tua tidak bisa mengajukan izin untuk anak yang tidak tertaut', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anakOrangLain = Siswa::factory()->create();

    $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anakOrangLain->id,
        'tipe' => 'izin',
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-17',
        'alasan' => 'Ada keperluan keluarga.',
    ])->assertForbidden();

    $this->assertDatabaseCount('izin_orang_tuas', 0);
});

test('halaman izin hanya menampilkan anak dan pengajuan milik orang tua aktif', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create(['nama' => 'Aisyah Putri']);
    $orangTua->siswas()->attach($anak);

    $izin = IzinOrangTua::factory()->create([
        'user_id' => $orangTua->id,
        'siswa_id' => $anak->id,
    ]);
    IzinOrangTua::factory()->create();

    $this->actingAs($orangTua)->get(route('orang-tua.izin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('orang-tua/Izin')
            ->has('anak', 1)
            ->where('anak.0.nama', 'Aisyah Putri')
            ->has('izins', 1)
            ->where('izins.0.id', $izin->id));
});

test('jenis cuti ditolak untuk pengajuan anak', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);

    $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anak->id,
        'tipe' => 'cuti',
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-17',
        'alasan' => 'Tidak masuk sekolah.',
    ])->assertSessionHasErrors('tipe');
});

test('halaman pengajuan membatasi tanggal pada tahun ajaran aktif', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);

    $this->actingAs($orangTua)
        ->get(route('orang-tua.izin.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tanggalMinimum', '2026-09-16')
            ->where('tanggalMaximum', '2027-06-30'));
});

test('tanggal pengajuan wajib berada dalam tahun ajaran aktif', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);

    $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anak->id,
        'tipe' => 'izin',
        'tanggal_mulai' => '2027-07-01',
        'tanggal_selesai' => '2027-07-02',
        'alasan' => 'Ada keperluan keluarga.',
    ])->assertSessionHasErrors(['tanggal_mulai', 'tanggal_selesai']);

    $this->assertDatabaseCount('izin_orang_tuas', 0);
});

test('pengajuan yang bertabrakan untuk anak yang sama ditolak', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);
    IzinOrangTua::factory()->create([
        'user_id' => $orangTua->id,
        'siswa_id' => $anak->id,
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-18',
    ]);

    $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anak->id,
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-18',
        'tanggal_selesai' => '2026-09-19',
        'alasan' => 'Masih perlu beristirahat.',
    ])->assertSessionHasErrors('tanggal_mulai');

    expect(IzinOrangTua::query()->count())->toBe(1);
});

test('lampiran baru dihapus ketika pengajuan gagal disimpan', function () {
    Storage::fake('local');
    $this->withoutExceptionHandling();
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER gagalkan_simpan_izin_orang_tua
        BEFORE INSERT ON izin_orang_tuas
        BEGIN
            SELECT RAISE(ABORT, 'sengaja gagal');
        END;
        SQL);

    expect(fn () => $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anak->id,
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-17',
        'alasan' => 'Demam dan perlu beristirahat.',
        'lampiran' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
    ]))->toThrow(QueryException::class);

    expect(Storage::disk('local')->allFiles('izin-orang-tua'))->toBe([]);
});

test('orang tua hanya dapat mengunduh lampiran pengajuannya sendiri', function () {
    Storage::fake('local');
    $pemilik = User::factory()->orangTua()->create();
    $orangTuaLain = User::factory()->orangTua()->create();
    $izin = IzinOrangTua::factory()->create([
        'user_id' => $pemilik->id,
        'lampiran_path' => 'izin-orang-tua/surat.pdf',
    ]);
    Storage::disk('local')->put('izin-orang-tua/surat.pdf', 'isi');

    $this->actingAs($pemilik)
        ->get(route('orang-tua.izin.lampiran', $izin))
        ->assertOk();
    $this->actingAs($orangTuaLain)
        ->get(route('orang-tua.izin.lampiran', $izin))
        ->assertForbidden();
});

test('form izin anak dapat menyusut pada layar mobile', function () {
    $halaman = file_get_contents(resource_path('js/pages/orang-tua/Izin.svelte'));

    expect($halaman)
        ->toContain('class="grid min-w-0 gap-4"')
        ->toContain('class="w-full min-w-0 max-w-full')
        ->toContain('class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2"');
});

test('menu dan halaman orang tua menyediakan pengajuan izin anak', function () {
    $navigasi = file_get_contents(resource_path('js/components/OrangTuaBottomNavigation.svelte'));
    $halaman = file_get_contents(resource_path('js/pages/orang-tua/Izin.svelte'));

    expect($navigasi)
        ->toContain('Izin anak')
        ->toContain('@/routes/orang-tua/izin')
        ->and($halaman)
        ->toContain('Ajukan izin anak')
        ->toContain('Pilih anak')
        ->toContain('Izin')
        ->toContain('Sakit')
        ->toContain('Riwayat pengajuan');
});
