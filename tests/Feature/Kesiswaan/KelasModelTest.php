<?php

use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Database\QueryException;

test('kelas baru distempel tahun ajaran yang sedang dilihat', function () {
    // aktifkan(), bukan factory(['is_active' => true]): migrasi data lama
    // sudah membuat satu tahun ajaran aktif duluan, jadi cara ini yang
    // menonaktifkannya supaya hanya satu baris yang aktif -- persis invarian
    // yang dijaga TahunAjaranScope.
    $tahun = TahunAjaran::factory()->create();
    $tahun->aktifkan();
    app(TahunAjaranTerpilih::class)->lupakan();

    $kelas = Kelas::create([
        'kantor_id' => Kantor::factory()->create()->id,
        'nama' => '5A',
        'tingkat' => 5,
        'is_active' => true,
    ]);

    expect($kelas->tahun_ajaran_id)->toBe($tahun->id);
});

test('nama kelas boleh sama di kantor berbeda', function () {
    TahunAjaran::factory()->create(['is_active' => true]);

    Kelas::factory()->create(['kantor_id' => Kantor::factory()->create()->id, 'nama' => '7A']);
    Kelas::factory()->create(['kantor_id' => Kantor::factory()->create()->id, 'nama' => '7A']);

    expect(Kelas::count())->toBe(2);
});

test('nama kelas kembar di kantor dan tahun yang sama ditolak', function () {
    TahunAjaran::factory()->create(['is_active' => true]);
    $kantor = Kantor::factory()->create();

    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);
})->throws(QueryException::class);

test('kelas tahun lalu tidak bocor ke tahun aktif', function () {
    $lalu = TahunAjaran::factory()->create(['nama' => '2024/2025', 'is_active' => true]);
    $kantor = Kantor::factory()->create();
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    $sekarang = TahunAjaran::factory()->create(['nama' => '2025/2026']);
    $sekarang->aktifkan();
    app(TahunAjaranTerpilih::class)->lupakan();

    expect(Kelas::count())->toBe(0)
        ->and(Kelas::withoutGlobalScopes()->count())->toBe(1);

    // Nama yang sama boleh dipakai lagi di tahun baru.
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '5A']);

    expect(Kelas::count())->toBe(1);
});

test('wali kelas dilepas saat gurunya dihapus, kelasnya tetap ada', function () {
    TahunAjaran::factory()->create(['is_active' => true]);
    $guru = User::factory()->create();
    $kelas = Kelas::factory()->create(['wali_kelas_id' => $guru->id]);

    $guru->delete();

    expect($kelas->refresh()->wali_kelas_id)->toBeNull();
});
