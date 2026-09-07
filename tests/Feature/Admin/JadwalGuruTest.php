<?php

use App\Models\JadwalKerja;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;

beforeEach(function () {
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka jadwal guru', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.jadwal-guru.index'))->assertForbidden();
});

test('admin melihat daftar guru dengan jadwal default', function () {
    User::factory()->create(['name' => 'Bu Ani']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.jadwal-guru.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/JadwalGuru')
            ->has('gurus', 1)
            ->where('gurus.0.punya_jadwal_sendiri', false)
            ->has('gurus.0.jadwals', 7)
            ->where('gurus.0.jadwals.1.jam_masuk', '07:00'));
});

test('admin menyimpan jadwal khusus satu guru', function () {
    $guru = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.jadwal-guru.update', $guru), ['jadwals' => [
            ['day_of_week' => 1, 'jam_masuk' => '09:00', 'jam_pulang' => '15:00', 'is_hari_kerja' => true],
        ]])
        ->assertRedirect(route('admin.jadwal-guru.index'));

    expect(JadwalKerja::hariUntukGuru((int) $guru->id, 1)?->jam_masuk)->toBe('09:00:00');
});

test('jadwal guru lain tetap memakai default', function () {
    $guru = User::factory()->create();
    $lain = User::factory()->create();

    JadwalKerja::create(['user_id' => $guru->id, 'day_of_week' => 1, 'jam_masuk' => '09:00:00', 'jam_pulang' => '15:00:00', 'is_hari_kerja' => true]);

    expect(JadwalKerja::hariUntukGuru((int) $guru->id, 1)?->jam_masuk)->toBe('09:00:00')
        ->and(JadwalKerja::hariUntukGuru((int) $lain->id, 1)?->jam_masuk)->toBe('07:00:00');
});

test('jadwal guru bisa dikembalikan ke default', function () {
    $guru = User::factory()->create();
    JadwalKerja::create(['user_id' => $guru->id, 'day_of_week' => 1, 'jam_masuk' => '09:00:00', 'jam_pulang' => '15:00:00', 'is_hari_kerja' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.jadwal-guru.destroy', $guru))
        ->assertRedirect(route('admin.jadwal-guru.index'));

    expect(JadwalKerja::where('user_id', $guru->id)->count())->toBe(0)
        ->and(JadwalKerja::hariUntukGuru((int) $guru->id, 1)?->jam_masuk)->toBe('07:00:00');
});

test('jadwal guru dihapus ikut saat akunnya dihapus', function () {
    $guru = User::factory()->create();
    JadwalKerja::create(['user_id' => $guru->id, 'day_of_week' => 1, 'jam_masuk' => '09:00:00', 'jam_pulang' => '15:00:00', 'is_hari_kerja' => true]);

    $guru->delete();

    expect(JadwalKerja::whereNotNull('user_id')->count())->toBe(0);
});
