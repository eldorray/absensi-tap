<?php

// tests/Feature/IzinTest.php

use App\Enums\StatusIzin;
use App\Models\Izin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-09-01 08:00:00');
});

test('guru bisa mengajukan izin', function () {
    $guru = User::factory()->create();

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-11',
        'alasan' => 'Demam tinggi.',
    ])->assertRedirect(route('izin.index'));

    $izin = Izin::where('user_id', $guru->id)->firstOrFail();

    expect($izin->status)->toBe(StatusIzin::Pending)
        ->and($izin->lampiran_path)->toBeNull();
});

test('lampiran disimpan di disk privat', function () {
    Storage::fake('local');

    $guru = User::factory()->create();

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-10',
        'alasan' => 'Surat dokter terlampir.',
        'lampiran' => UploadedFile::fake()->create('surat.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $path = Izin::where('user_id', $guru->id)->value('lampiran_path');

    expect($path)->toStartWith('izin/');
    Storage::disk('local')->assertExists($path);
});

test('lampiran bukan pdf atau gambar ditolak', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('izin.store'), [
        'tipe' => 'izin',
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-10',
        'alasan' => 'Ada acara keluarga.',
        'lampiran' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
    ])->assertSessionHasErrors('lampiran');
});

test('tanggal selesai tidak boleh sebelum tanggal mulai', function () {
    $this->actingAs(User::factory()->create())->post(route('izin.store'), [
        'tipe' => 'izin',
        'tanggal_mulai' => '2026-09-11',
        'tanggal_selesai' => '2026-09-10',
        'alasan' => 'Salah isi.',
    ])->assertSessionHasErrors('tanggal_selesai');
});

test('izin yang tumpang tindih dengan pengajuan sendiri ditolak', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->create([
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-12',
    ]);

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-12',
        'tanggal_selesai' => '2026-09-13',
        'alasan' => 'Masih sakit.',
    ])->assertSessionHasErrors('tanggal_mulai');

    expect(Izin::where('user_id', $guru->id)->count())->toBe(1);
});

test('izin yang ditolak tidak menghalangi pengajuan baru', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->ditolak()->create([
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-12',
    ]);

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-11',
        'tanggal_selesai' => '2026-09-11',
        'alasan' => 'Pengajuan ulang.',
    ])->assertSessionHasNoErrors();
});

test('guru hanya melihat izin miliknya sendiri', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->count(2)->create();
    Izin::factory()->for(User::factory())->count(3)->create();

    $this->actingAs($guru)
        ->get(route('izin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('izin/Index')->has('izins', 2));
});

test('guru tidak bisa mengunduh lampiran izin guru lain', function () {
    Storage::fake('local');

    $milikOrangLain = Izin::factory()->for(User::factory())->create([
        'lampiran_path' => 'izin/rahasia.pdf',
    ]);

    Storage::disk('local')->put('izin/rahasia.pdf', 'isi');

    $this->actingAs(User::factory()->create())
        ->get(route('izin.lampiran', $milikOrangLain))
        ->assertForbidden();
});

test('guru bisa mengunduh lampirannya sendiri', function () {
    Storage::fake('local');

    $guru = User::factory()->create();
    $izin = Izin::factory()->for($guru)->create(['lampiran_path' => 'izin/surat.pdf']);

    Storage::disk('local')->put('izin/surat.pdf', 'isi');

    $this->actingAs($guru)->get(route('izin.lampiran', $izin))->assertOk();
});

test('izin tanpa lampiran mengembalikan 404 saat diunduh', function () {
    $guru = User::factory()->create();
    $izin = Izin::factory()->for($guru)->create(['lampiran_path' => null]);

    $this->actingAs($guru)->get(route('izin.lampiran', $izin))->assertNotFound();
});
