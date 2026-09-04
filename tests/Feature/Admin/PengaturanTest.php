<?php

use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
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
test('radius dibatasi', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.lokasi.store'), ['nama' => 'X', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meter' => 50000])->assertSessionHasErrors('radius_meter');
});
test('koordinat dibatasi', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.lokasi.store'), ['nama' => 'X', 'latitude' => 200, 'longitude' => 106.8, 'radius_meter' => 80])->assertSessionHasErrors('latitude');
});
test('admin mengubah jadwal', function () {
    $this->seed(JadwalKerjaSeeder::class);
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.jadwal.update'), ['jadwals' => [['day_of_week' => 1, 'jam_masuk' => '06:30', 'jam_pulang' => '13:30', 'toleransi_menit' => 5, 'is_hari_kerja' => true]]])->assertRedirect();
    expect(JadwalKerja::where('day_of_week', 1)->value('toleransi_menit'))->toBe(5);
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
