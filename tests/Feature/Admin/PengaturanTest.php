<?php

use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\PengaturanAbsensi;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;

test('guru tidak boleh membuka pengaturan', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.pengaturan.edit'))->assertForbidden();
});
test('admin melihat pengaturan', function () {
    $this->seed(JadwalKerjaSeeder::class);
    Lokasi::factory()->create();
    HariLibur::factory()->create();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.pengaturan.edit'))->assertOk()->assertInertia(fn ($p) => $p->component('admin/Pengaturan')->has('lokasis', 1)->has('jadwals', 7)->has('hariLiburs', 1));
});
test('admin bisa menambah lokasi', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.lokasi.store'), ['nama' => 'Gerbang', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 80, 'is_active' => true])->assertRedirect(route('admin.pengaturan.edit'));
    expect(Lokasi::where('nama', 'Gerbang')->value('radius_meter'))->toBe(80);
});
test('admin mengubah lokasi', function () {
    $lokasi = Lokasi::factory()->create(['nama' => 'Gerbang', 'radius_meter' => 80]);
    $this->actingAs(User::factory()->admin()->create())->patch(route('admin.lokasi.update', $lokasi), ['nama' => 'Gerbang Belakang', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 120, 'is_active' => false])->assertRedirect(route('admin.pengaturan.edit'));
    $lokasi->refresh();
    expect($lokasi->nama)->toBe('Gerbang Belakang')
        ->and($lokasi->radius_meter)->toBe(120)
        ->and($lokasi->is_active)->toBeFalse();
});
test('guru tidak bisa mengubah lokasi', function () {
    $lokasi = Lokasi::factory()->create(['nama' => 'Gerbang']);
    $this->actingAs(User::factory()->create())->patch(route('admin.lokasi.update', $lokasi), ['nama' => 'Rumah', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 120])->assertForbidden();
    expect($lokasi->refresh()->nama)->toBe('Gerbang');
});
test('admin menghapus lokasi selama masih ada lokasi aktif lain', function () {
    Lokasi::factory()->create(['is_active' => true]);
    $lokasi = Lokasi::factory()->create(['is_active' => true]);
    $this->actingAs(User::factory()->admin()->create())->delete(route('admin.lokasi.destroy', $lokasi))->assertRedirect(route('admin.pengaturan.edit'));
    expect(Lokasi::count())->toBe(1);
});
test('lokasi aktif terakhir tidak bisa dihapus', function () {
    $lokasi = Lokasi::factory()->create(['is_active' => true]);
    Lokasi::factory()->create(['is_active' => false]);
    $this->actingAs(User::factory()->admin()->create())->delete(route('admin.lokasi.destroy', $lokasi))->assertRedirect(route('admin.pengaturan.edit'));
    expect(Lokasi::whereKey($lokasi->id)->exists())->toBeTrue();
});
test('radius dibatasi', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.lokasi.store'), ['nama' => 'X', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 50000])->assertSessionHasErrors('radius_meter');
});
test('koordinat dibatasi', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.lokasi.store'), ['nama' => 'X', 'latitude' => 200, 'longitude' => 106.8, 'radius_meter' => 80])->assertSessionHasErrors('latitude');
});
test('admin mengubah jadwal', function () {
    $this->seed(JadwalKerjaSeeder::class);
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.jadwal.update'), ['jadwals' => [['day_of_week' => 1, 'jam_masuk' => '06:30', 'jam_pulang' => '13:30', 'is_hari_kerja' => true]]])->assertRedirect();
    $jadwal = JadwalKerja::whereNull('user_id')->where('day_of_week', 1)->firstOrFail();
    expect($jadwal->jam_masuk)->toBe('06:30:00')
        ->and($jadwal->jam_pulang)->toBe('13:30:00');
});
test('admin mengubah aturan jam absen global', function () {
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.pengaturan-absensi.update'), ['toleransi_menit' => 5, 'buka_masuk_menit' => 45, 'tutup_masuk_menit' => 90, 'buka_pulang_menit' => 15])->assertRedirect(route('admin.pengaturan.edit'));
    $pengaturan = PengaturanAbsensi::current();
    expect($pengaturan->toleransi_menit)->toBe(5)
        ->and($pengaturan->buka_masuk_menit)->toBe(45)
        ->and($pengaturan->tutup_masuk_menit)->toBe(90)
        ->and($pengaturan->buka_pulang_menit)->toBe(15);
});
test('guru tidak bisa mengubah aturan jam absen', function () {
    $this->actingAs(User::factory()->create())->put(route('admin.pengaturan-absensi.update'), ['toleransi_menit' => 5, 'buka_masuk_menit' => 45, 'tutup_masuk_menit' => 90, 'buka_pulang_menit' => 15])->assertForbidden();
});
test('admin menambah dan menghapus libur', function () {
    $a = User::factory()->admin()->create();
    $this->actingAs($a)->post(route('admin.hari-libur.store'), ['tanggal' => '2026-08-17', 'nama' => 'HUT RI']);
    $h = HariLibur::firstOrFail();
    $this->actingAs($a)->delete(route('admin.hari-libur.destroy', $h));
    expect(HariLibur::count())->toBe(0);
});
test('tanggal libur unik', function () {
    HariLibur::factory()->create(['tanggal' => '2026-08-17']);
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.hari-libur.store'), ['tanggal' => '2026-08-17', 'nama' => 'X'])->assertSessionHasErrors('tanggal');
});
