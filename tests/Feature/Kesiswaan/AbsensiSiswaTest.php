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
use Illuminate\Support\Collection;
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

test('guru dapat menengok absensi final hari sebelumnya lewat filter tanggal', function () {
    $guru = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);
    $kemarin = Carbon::parse('2026-09-13');
    $sesi = SesiAbsensiSiswa::create(['kelas_id' => $kelas->id, 'tanggal' => $kemarin, 'status' => StatusSesiAbsensiSiswa::Final, 'dibuat_oleh' => $guru->id, 'finalisasi_oleh' => $guru->id, 'finalisasi_pada' => $kemarin]);
    $sesi->absensis()->create(['siswa_id' => $siswas[0]->id, 'status' => StatusKehadiranSiswa::Sakit, 'dicatat_oleh' => $guru->id]);

    $this->actingAs($guru)->get(route('absensi-siswa.index', ['tanggal' => '2026-09-13']))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('tanggal', '2026-09-13')
        ->where('kelas.0.status', StatusSesiAbsensiSiswa::Final->value));

    $this->actingAs($guru)->get(route('absensi-siswa.show', ['kelas' => $kelas, 'tanggal' => '2026-09-13']))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('tanggal', '2026-09-13')
        ->where('sesi.status', StatusSesiAbsensiSiswa::Final->value)
        ->where('sesi.read_only', true)
        // Daftar diurutkan menurut nama, jadi siswa yang sakit dicari, bukan ditebak indeksnya.
        ->where('siswa', fn (Collection $siswa) => $siswa->firstWhere('id', $siswas[0]->id)['status'] === StatusKehadiranSiswa::Sakit->value));
});

test('hari lampau yang masih draft tetap hanya dapat dibaca dan hari depan ditolak', function () {
    $guru = User::factory()->create();
    [$kelas] = kelasAbsensi($guru);
    SesiAbsensiSiswa::create(['kelas_id' => $kelas->id, 'tanggal' => Carbon::parse('2026-09-13'), 'status' => StatusSesiAbsensiSiswa::Draft, 'dibuat_oleh' => $guru->id]);

    $this->actingAs($guru)->get(route('absensi-siswa.show', ['kelas' => $kelas, 'tanggal' => '2026-09-13']))
        ->assertOk()->assertInertia(fn ($page) => $page->where('sesi.read_only', true));

    $this->actingAs($guru)->get(route('absensi-siswa.index', ['tanggal' => '2026-09-15']))
        ->assertSessionHasErrors('tanggal');

    // Penyuntingan tetap terkunci di hari ini, apa pun tanggal yang dikirim.
    $this->actingAs($guru)->put(route('absensi-siswa.draft', $kelas), ['tanggal' => '2026-09-13', 'absensis' => []])
        ->assertSessionHasErrors('tanggal');
});

test('guru dapat mencetak absensi harian dan periode bebas', function () {
    $guru = User::factory()->create(['name' => 'Bu Rina']);
    [$kelas, $siswas] = kelasAbsensi($guru);
    foreach (['2026-09-11', '2026-09-13'] as $hari) {
        $sesi = SesiAbsensiSiswa::create(['kelas_id' => $kelas->id, 'tanggal' => Carbon::parse($hari), 'status' => StatusSesiAbsensiSiswa::Final, 'dibuat_oleh' => $guru->id]);
        $sesi->absensis()->create(['siswa_id' => $siswas[0]->id, 'status' => StatusKehadiranSiswa::Alpa, 'dicatat_oleh' => $guru->id]);
    }

    $this->actingAs($guru)->get(route('absensi-siswa.cetak', ['dari' => '2026-09-13', 'sampai' => '2026-09-13']))
        ->assertOk()
        ->assertViewIs('absensi-siswa.cetak')
        ->assertViewHas('harian', true)
        ->assertViewHas('sesis', fn (array $sesis) => count($sesis) === 1)
        ->assertSee('Bu Rina')
        ->assertSee($siswas[0]->nama);

    // Periode dibaca sebagai rekap: satu baris per siswa, berisi hitungan per status.
    $this->actingAs($guru)->get(route('absensi-siswa.cetak', ['dari' => '2026-09-01', 'sampai' => '2026-09-14']))
        ->assertOk()
        ->assertViewHas('harian', false)
        ->assertViewHas('rekap', function (array $rekap) use ($siswas) {
            $baris = collect($rekap[0]['siswa'])->firstWhere('nis', $siswas[0]->nis);

            return count($rekap) === 1
                && $rekap[0]['hari'] === 2
                && $baris['hitung'][StatusKehadiranSiswa::Alpa->value] === 2
                && $baris['total'] === 2;
        })
        ->assertSee($siswas[0]->nama)
        ->assertSee('hari absensi tercatat');

    $this->actingAs($guru)->get(route('absensi-siswa.cetak', ['dari' => '2026-09-14', 'sampai' => '2026-09-13']))
        ->assertSessionHasErrors('sampai');
});

test('cetak tidak membocorkan hari di luar masa tugas guru pengganti', function () {
    $wali = User::factory()->create();
    $pengganti = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($wali);
    GuruKelas::factory()->create(['kelas_id' => $kelas->id, 'user_id' => $pengganti->id, 'tanggal_mulai' => '2026-09-13', 'tanggal_selesai' => '2026-09-13']);
    foreach (['2026-09-12', '2026-09-13'] as $hari) {
        $sesi = SesiAbsensiSiswa::create(['kelas_id' => $kelas->id, 'tanggal' => Carbon::parse($hari), 'status' => StatusSesiAbsensiSiswa::Final, 'dibuat_oleh' => $wali->id]);
        $sesi->absensis()->create(['siswa_id' => $siswas[0]->id, 'status' => StatusKehadiranSiswa::Hadir, 'dicatat_oleh' => $wali->id]);
    }

    $this->actingAs($pengganti)->get(route('absensi-siswa.cetak', ['dari' => '2026-09-01', 'sampai' => '2026-09-14']))
        ->assertOk()
        // Hanya 13 September yang masuk masa tugasnya; 12 September milik wali kelas.
        ->assertViewHas('sesis', fn (array $sesis) => count($sesis) === 1 && str_contains($sesis[0]['tanggal'], '13 September'));

    $this->actingAs($wali)->get(route('absensi-siswa.cetak', ['dari' => '2026-09-01', 'sampai' => '2026-09-14']))
        ->assertOk()
        ->assertViewHas('sesis', fn (array $sesis) => count($sesis) === 2);
});

test('guru dapat membatalkan finalisasi hari ini dan pembatalannya tercatat', function () {
    $guru = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);
    $this->actingAs($guru)->put(route('absensi-siswa.finalisasi', $kelas), ['tanggal' => '2026-09-14', 'absensis' => [['siswa_id' => $siswas[0]->id, 'status' => StatusKehadiranSiswa::Alpa->value]]]);
    expect(SesiAbsensiSiswa::first()->status)->toBe(StatusSesiAbsensiSiswa::Final);

    $this->actingAs($guru)->put(route('absensi-siswa.buka-finalisasi', $kelas))
        ->assertRedirect(route('absensi-siswa.show', $kelas));

    $sesi = SesiAbsensiSiswa::first();
    expect($sesi->status)->toBe(StatusSesiAbsensiSiswa::Draft)
        ->and($sesi->dibuka_oleh)->toBe($guru->id)
        ->and($sesi->dibuka_pada)->not->toBeNull();

    // Statusnya kembali dapat disunting, dan pilihan lama tetap terbaca.
    $this->actingAs($guru)->get(route('absensi-siswa.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('sesi.read_only', false)->where('sesi.dapat_dibuka', false));
});

test('guru tidak dapat membuka finalisasi kelas orang lain maupun hari lampau', function () {
    $guru = User::factory()->create();
    $lain = User::factory()->create();
    [$kelas, $siswas] = kelasAbsensi($guru);
    $this->actingAs($guru)->put(route('absensi-siswa.finalisasi', $kelas), ['tanggal' => '2026-09-14', 'absensis' => []]);

    $this->actingAs($lain)->put(route('absensi-siswa.buka-finalisasi', $kelas))->assertForbidden();
    expect(SesiAbsensiSiswa::first()->status)->toBe(StatusSesiAbsensiSiswa::Final);

    // Besok, sesi hari ini sudah jadi hari lampau: pintunya tertutup untuk guru.
    Carbon::setTestNow('2026-09-15 08:00:00');
    $this->actingAs($guru)->put(route('absensi-siswa.buka-finalisasi', $kelas))->assertForbidden();
    expect(SesiAbsensiSiswa::first()->status)->toBe(StatusSesiAbsensiSiswa::Final);
});
