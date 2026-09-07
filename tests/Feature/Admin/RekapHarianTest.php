<?php

use App\Enums\HasilTap;
use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // 2026-09-07 = Senin, hari kerja 07:00-14:00 menurut seeder.
    Carbon::setTestNow('2026-09-07 10:00:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka rekap harian', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rekap-harian.index'))->assertForbidden();
});

test('rekap harian default memakai tanggal hari ini', function () {
    User::factory()->create(['name' => 'Bu Ani']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/RekapHarian')
            ->where('rekap.tanggal', '2026-09-07')
            ->has('rekap.baris', 1)
            ->where('rekap.baris.0.nama', 'Bu Ani')
            ->where('rekap.baris.0.jadwal', '07:00-14:00'));
});

test('guru yang belum absen berstatus belum, bukan alfa, pada hari yang masih berjalan', function () {
    User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index'))
        ->assertInertia(fn ($p) => $p->where('rekap.baris.0.status', 'belum')
            ->where('rekap.ringkasan.belum', 1));
});

test('rekap harian memuat jam, lokasi, dan jarak dari tap guru', function () {
    // 07:30: lewat toleransi 07:10 tapi masih di dalam jendela masuk (tutup 09:00).
    Carbon::setTestNow('2026-09-07 07:30:00');

    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index'))
        ->assertInertia(fn ($p) => $p->where('rekap.baris.0.status', 'terlambat')
            ->where('rekap.baris.0.jam_masuk', '07:30')
            ->where('rekap.baris.0.jam_pulang', null)
            ->where('rekap.baris.0.lokasi', 'Gerbang Utama')
            ->where('rekap.baris.0.jarak_meter', 0)
            ->where('rekap.baris.0.anomali', ['tanpa_biometrik', 'belum_tap_pulang']));
});

test('izin yang disetujui muncul di rekap harian', function () {
    $guru = User::factory()->create();
    Izin::factory()->for($guru)->create([
        'tipe' => TipeIzin::Sakit,
        'status' => StatusIzin::Disetujui,
        'tanggal_mulai' => '2026-09-06',
        'tanggal_selesai' => '2026-09-08',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index'))
        ->assertInertia(fn ($p) => $p->where('rekap.baris.0.status', 'sakit'));
});

test('hari libur menutup seluruh baris rekap harian', function () {
    User::factory()->create();
    HariLibur::factory()->create(['tanggal' => '2026-09-07']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index'))
        ->assertInertia(fn ($p) => $p->where('rekap.baris.0.status', 'libur'));
});

test('jadwal khusus guru dipakai rekap harian', function () {
    $guru = User::factory()->create();
    JadwalKerja::create([
        'user_id' => $guru->id,
        'day_of_week' => 1,
        'jam_masuk' => '09:00:00',
        'jam_pulang' => '16:00:00',
        'is_hari_kerja' => true,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index'))
        ->assertInertia(fn ($p) => $p->where('rekap.baris.0.jadwal', '09:00-16:00'));
});

test('tanggal lampau tanpa absen berstatus alfa', function () {
    User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index', ['tanggal' => '2026-09-01']))
        ->assertInertia(fn ($p) => $p->where('rekap.tanggal', '2026-09-01')
            ->where('rekap.baris.0.status', 'alfa'));
});

test('tanggal tidak sah ditolak', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.index', ['tanggal' => '07-09-2026']))
        ->assertSessionHasErrors('tanggal');
});

test('export rekap harian berupa csv', function () {
    [$guru] = guruSiapAbsen();

    $respons = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap-harian.export', ['tanggal' => '2026-09-07']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($respons->streamedContent())
        ->toContain('NIP,Nama,Jadwal,Masuk,Pulang,Status,Lokasi,"Jarak (m)",Catatan')
        ->toContain($guru->name);
});

test('guru tidak bisa mengunduh export rekap harian', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.rekap-harian.export'))
        ->assertForbidden();
});

test('admin mereset absen satu guru dan tap-nya bisa diulang', function () {
    Carbon::setTestNow('2026-09-07 07:30:00');

    [$guru, $perangkat] = guruSiapAbsen();
    $payload = [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ];

    $this->actingAs($guru)->post(route('absensi.store'), $payload)->assertSessionHasNoErrors();
    expect(Absensi::where('user_id', $guru->id)->count())->toBe(1);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.rekap-harian.reset', $guru), ['tanggal' => '2026-09-07'])
        ->assertRedirect(route('admin.rekap-harian.index', ['tanggal' => '2026-09-07']));

    expect(Absensi::where('user_id', $guru->id)->count())->toBe(0)
        // Jejak tap tetap ada: itu bukti jam dan koordinatnya.
        ->and(AbsensiAttempt::where('user_id', $guru->id)->where('hasil', HasilTap::Diterima)->count())->toBe(1);

    // Guru bisa absen ulang sesudah direset, bukan ditolak duplikat.
    $this->actingAs($guru)->post(route('absensi.store'), $payload)->assertSessionHasNoErrors();

    expect(Absensi::where('user_id', $guru->id)->count())->toBe(1);
});

test('reset tanggal lain tidak menyentuh absen hari ini', function () {
    Carbon::setTestNow('2026-09-07 07:30:00');

    [$guru, $perangkat] = guruSiapAbsen();
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.rekap-harian.reset', $guru), ['tanggal' => '2026-09-01']);

    expect(Absensi::where('user_id', $guru->id)->count())->toBe(1);
});

test('reset tanpa absen tidak menimbulkan galat', function () {
    $guru = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.rekap-harian.reset', $guru), ['tanggal' => '2026-09-07'])
        ->assertRedirect();
});

test('guru tidak bisa mereset absen', function () {
    Carbon::setTestNow('2026-09-07 07:30:00');

    [$guru, $perangkat] = guruSiapAbsen();
    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ]);

    $this->actingAs($guru)
        ->delete(route('admin.rekap-harian.reset', $guru), ['tanggal' => '2026-09-07'])
        ->assertForbidden();

    expect(Absensi::where('user_id', $guru->id)->count())->toBe(1);
});

test('akun admin tidak bisa direset lewat rekap harian', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.rekap-harian.reset', $admin), ['tanggal' => '2026-09-07'])
        ->assertNotFound();
});

test('tombol reset ada di setiap baris guru yang sudah absen', function () {
    $halaman = file_get_contents(resource_path('js/pages/admin/RekapHarian.svelte'));

    expect($halaman)
        ->toContain('onclick={() => resetAbsen(b)}')
        // Hanya baris yang sudah ada tapnya.
        ->toContain('{#if b.jam_masuk !== null}')
        ->toContain('rekapHarianReset(baris.user_id)');
});
