<?php

use App\Enums\StatusKehadiranSiswa;
use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\SesiAbsensiSiswa;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
    Carbon::setTestNow('2026-09-14 08:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function kelasAbsensi(User $guru): array
{
    $kantor = Kantor::factory()->create(['nama' => 'MI']);
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id, 'wali_kelas_id' => $guru->id, 'nama' => '5A']);
    $siswas = Siswa::factory()->count(3)->create(['kantor_id' => $kantor->id]);
    foreach ($siswas as $siswa) {
        AnggotaKelas::factory()->create(['kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'tanggal_mulai' => '2026-07-01']);
    }

    return [$kelas, $siswas];
}

test('guru berwenang melihat daftar kelas dan semua siswa hadir secara visual sebelum sesi resmi ada', function () {
    $guru = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);

    $this->actingAs($guru)->get(route('absensi-siswa.index'))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('absensi-siswa/Index')
        ->where('kelas.0.id', $kelas->id)
        ->where('kelas.0.jumlah_siswa', 3)
        ->where('kelas.0.status', StatusSesiAbsensiSiswa::BelumDiperiksa->value));

    $this->actingAs($guru)->get(route('absensi-siswa.show', $kelas))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('absensi-siswa/Show')
        ->has('siswa', 3)
        ->where('siswa.0.status', StatusKehadiranSiswa::Hadir->value)
        ->where('sesi.status', StatusSesiAbsensiSiswa::BelumDiperiksa->value));

    expect(SesiAbsensiSiswa::count())->toBe(0)->and(AbsensiSiswa::count())->toBe(0);
});

test('guru dapat menyimpan draft pengecualian dan server membuat snapshot semua anggota', function () {
    $guru = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);

    $this->actingAs($guru)->put(route('absensi-siswa.draft', $kelas), [
        'tanggal' => '2026-09-14',
        'catatan' => 'Pemeriksaan pagi',
        'absensis' => [
            ['siswa_id' => $siswas[1]->id, 'status' => 'sakit', 'catatan' => 'Demam'],
        ],
    ])->assertRedirect(route('absensi-siswa.show', $kelas));

    $sesi = SesiAbsensiSiswa::firstOrFail();
    expect($sesi->status)->toBe(StatusSesiAbsensiSiswa::Draft)
        ->and($sesi->dibuat_oleh)->toBe($guru->id)
        ->and($sesi->absensis)->toHaveCount(3);
    $this->assertDatabaseHas('absensi_siswas', ['siswa_id' => $siswas[0]->id, 'status' => 'hadir']);
    $this->assertDatabaseHas('absensi_siswas', ['siswa_id' => $siswas[1]->id, 'status' => 'sakit', 'catatan' => 'Demam']);
});

test('finalisasi langsung semua hadir idempotent dan sesi final menjadi read only', function () {
    $guru = User::factory()->create();
    [$kelas] = kelasAbsensi($guru);
    $payload = ['tanggal' => '2026-09-14', 'absensis' => []];

    $this->actingAs($guru)->put(route('absensi-siswa.finalisasi', $kelas), $payload)->assertRedirect();
    $this->actingAs($guru)->put(route('absensi-siswa.finalisasi', $kelas), $payload)->assertRedirect();

    $sesi = SesiAbsensiSiswa::firstOrFail();
    expect(SesiAbsensiSiswa::count())->toBe(1)
        ->and(AbsensiSiswa::count())->toBe(3)
        ->and($sesi->status)->toBe(StatusSesiAbsensiSiswa::Final)
        ->and($sesi->finalisasi_oleh)->toBe($guru->id)
        ->and($sesi->finalisasi_pada)->not->toBeNull();

    $this->actingAs($guru)->put(route('absensi-siswa.draft', $kelas), $payload)->assertForbidden();
    $this->actingAs($guru)->get(route('absensi-siswa.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('sesi.read_only', true));
});

test('snapshot hanya memakai anggota yang berlaku pada tanggal sesi', function () {
    $guru = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);
    AnggotaKelas::where('siswa_id', $siswas[0]->id)->update(['tanggal_selesai' => '2026-09-13', 'is_active' => false]);
    $baru = Siswa::factory()->create(['kantor_id' => $kelas->kantor_id]);
    AnggotaKelas::factory()->create(['kelas_id' => $kelas->id, 'siswa_id' => $baru->id, 'tanggal_mulai' => '2026-09-15']);

    $this->actingAs($guru)->put(route('absensi-siswa.finalisasi', $kelas), ['tanggal' => '2026-09-14', 'absensis' => []])->assertRedirect();

    expect(AbsensiSiswa::count())->toBe(2);
    $this->assertDatabaseMissing('absensi_siswas', ['siswa_id' => $siswas[0]->id]);
    $this->assertDatabaseMissing('absensi_siswas', ['siswa_id' => $baru->id]);
});

test('guru asing dan pengganti di luar masa tugas tidak boleh membuka atau menulis absensi', function () {
    $wali = User::factory()->create();
    $asing = User::factory()->create();
    [$kelas] = kelasAbsensi($wali);
    GuruKelas::factory()->create(['kelas_id' => $kelas->id, 'user_id' => $asing->id, 'tanggal_selesai' => '2026-09-13']);

    $this->actingAs($asing)->get(route('absensi-siswa.show', $kelas))->assertForbidden();
    $this->actingAs($asing)->put(route('absensi-siswa.finalisasi', $kelas), ['tanggal' => '2026-09-14', 'absensis' => []])->assertForbidden();
});

test('guru pengganti aktif dapat mengabsen dan payload menolak siswa non anggota serta status tidak valid', function () {
    $wali = User::factory()->create();
    $pengganti = User::factory()->create();
    [$kelas] = kelasAbsensi($wali);
    GuruKelas::factory()->create(['kelas_id' => $kelas->id, 'user_id' => $pengganti->id, 'tanggal_mulai' => '2026-09-14', 'tanggal_selesai' => '2026-09-14']);
    $asing = Siswa::factory()->create(['kantor_id' => $kelas->kantor_id]);

    $this->actingAs($pengganti)->get(route('absensi-siswa.show', $kelas))->assertOk();
    $this->actingAs($pengganti)->put(route('absensi-siswa.draft', $kelas), [
        'tanggal' => '2026-09-14',
        'absensis' => [['siswa_id' => $asing->id, 'status' => 'bolos']],
    ])->assertSessionHasErrors(['absensis.0.status']);
});

test('halaman mobile menyediakan pencarian filter bottom sheet ringkasan dan konfirmasi finalisasi', function () {
    $page = file_get_contents(resource_path('js/pages/absensi-siswa/Show.svelte'));
    $nav = file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte'));

    expect($page)->toContain('Cari nama atau NIS')
        ->toContain('Simpan draft')
        ->toContain('Finalisasi Absensi')
        ->toContain('Hasil akan dapat dilihat orang tua')
        ->toContain('type="submit"')
        ->toContain('fixed inset-x-0 bottom-0')
        ->and($nav)->toContain('@/routes/absensi-siswa')->not->toContain('Segera tersedia');
});

test('guru tidak dapat memalsukan tanggal sesi selain hari ini', function () {
    $guru = User::factory()->create();
    [$kelas] = kelasAbsensi($guru);

    $this->actingAs($guru)->put(route('absensi-siswa.draft', $kelas), [
        'tanggal' => '2026-09-13',
        'absensis' => [],
    ])->assertSessionHasErrors('tanggal');

    expect(SesiAbsensiSiswa::count())->toBe(0);
});

test('constraint database mencegah sesi dan detail ganda', function () {
    $guru = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);
    $sesi = SesiAbsensiSiswa::create(['kelas_id' => $kelas->id, 'tanggal' => '2026-09-14', 'status' => 'draft', 'dibuat_oleh' => $guru->id]);
    AbsensiSiswa::create(['sesi_absensi_siswa_id' => $sesi->id, 'siswa_id' => $siswas[0]->id, 'status' => 'hadir', 'dicatat_oleh' => $guru->id]);

    expect(fn () => DB::table('sesi_absensi_siswas')->insert([
        'tahun_ajaran_id' => $kelas->tahun_ajaran_id, 'kelas_id' => $kelas->id, 'tanggal' => $sesi->getRawOriginal('tanggal'), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
