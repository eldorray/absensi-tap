<?php

use App\Enums\StatusKehadiranSiswa;
use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\AbsensiSiswa;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\SesiAbsensiSiswa;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
    Carbon::setTestNow('2026-09-16 08:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function catatKehadiranOrangTua(Siswa $siswa, string $tanggal, StatusSesiAbsensiSiswa $status, StatusKehadiranSiswa $kehadiran): AbsensiSiswa
{
    $kelas = Kelas::factory()->create([
        'kantor_id' => $siswa->kantor_id,
        'nama' => 'Portal '.$tanggal,
    ]);
    $sesi = SesiAbsensiSiswa::query()->create([
        'kelas_id' => $kelas->id,
        'tanggal' => $tanggal,
        'status' => $status,
    ]);

    return AbsensiSiswa::query()->create([
        'sesi_absensi_siswa_id' => $sesi->id,
        'siswa_id' => $siswa->id,
        'status' => $kehadiran,
        'catatan' => $kehadiran === StatusKehadiranSiswa::Sakit ? 'Demam' : null,
        'dicatat_oleh' => User::factory()->create()->id,
    ]);
}

test('landing aplikasi mengarahkan setiap role ke wilayahnya', function () {
    $this->actingAs(User::factory()->orangTua()->create())
        ->get(route('aplikasi'))->assertRedirect(route('orang-tua.dashboard'));
    $this->actingAs(User::factory()->create())
        ->get(route('aplikasi'))->assertRedirect(route('dashboard'));
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('aplikasi'))->assertRedirect(route('admin.dashboard'));
});

test('login orang tua diarahkan ke landing aplikasi lalu portal', function () {
    $orangTua = User::factory()->orangTua()->create(['password' => 'password']);

    $this->post(route('login.store'), [
        'email' => $orangTua->email,
        'password' => 'password',
    ])->assertRedirect('/aplikasi');
});

test('hanya orang tua aktif yang dapat membuka portal', function () {
    $orangTua = User::factory()->orangTua()->create();

    $this->get(route('orang-tua.dashboard'))->assertRedirect(route('login'));
    $this->actingAs($orangTua)->get(route('orang-tua.dashboard'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('orang-tua.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('orang-tua.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->orangTua()->create(['is_active' => false]))
        ->get(route('orang-tua.dashboard'))->assertForbidden();
});

test('akun tanpa anak tertaut mendapat empty state yang aman', function () {
    $this->actingAs(User::factory()->orangTua()->create())
        ->get(route('orang-tua.dashboard'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-cache, private')
        ->assertInertia(fn ($page) => $page
            ->component('orang-tua/Index')
            ->has('anak', 0)
            ->where('siswaTerpilih', null)
            ->has('riwayat', 0));
});

test('orang tua melihat beberapa anak tetapi tidak melihat anak akun lain', function () {
    $kantor = Kantor::factory()->create();
    $orangTua = User::factory()->orangTua()->create();
    $orangTuaLain = User::factory()->orangTua()->create();
    $anakPertama = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'Aisyah']);
    $anakKedua = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'Budi']);
    $anakLain = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'Rahasia']);
    $orangTua->siswas()->attach([$anakPertama->id, $anakKedua->id]);
    $orangTuaLain->siswas()->attach($anakLain);

    $this->actingAs($orangTua)->get(route('orang-tua.dashboard', ['siswa' => $anakKedua->id]))
        ->assertInertia(fn ($page) => $page
            ->has('anak', 2)
            ->where('anak.0.nama', 'Aisyah')
            ->where('anak.1.nama', 'Budi')
            ->where('siswaTerpilih.id', $anakKedua->id)
            ->missing('anak.2'));

    $this->actingAs($orangTua)->get(route('orang-tua.dashboard', ['siswa' => $anakLain->id]))
        ->assertInertia(fn ($page) => $page
            ->where('siswaTerpilih.id', $anakPertama->id)
            ->has('anak', 2));
});

test('portal hanya menerbitkan absensi final dan dikoreksi milik anak tertaut', function () {
    $kantor = Kantor::factory()->create();
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'Anak Saya']);
    $anakLain = Siswa::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'Anak Lain']);
    $orangTua->siswas()->attach($anak);

    catatKehadiranOrangTua($anak, '2026-09-10', StatusSesiAbsensiSiswa::Draft, StatusKehadiranSiswa::Alpa);
    catatKehadiranOrangTua($anak, '2026-09-11', StatusSesiAbsensiSiswa::Final, StatusKehadiranSiswa::Hadir);
    catatKehadiranOrangTua($anak, '2026-09-12', StatusSesiAbsensiSiswa::Dikoreksi, StatusKehadiranSiswa::Sakit);
    catatKehadiranOrangTua($anakLain, '2026-09-13', StatusSesiAbsensiSiswa::Final, StatusKehadiranSiswa::Alpa);

    $this->actingAs($orangTua)->get(route('orang-tua.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('riwayat', 2)
            ->where('riwayat.0.status', 'sakit')
            ->where('riwayat.1.status', 'hadir')
            ->where('ringkasan.hadir', 1)
            ->where('ringkasan.sakit', 1)
            ->where('ringkasan.alpa', 0));
});

test('hasil yang dibuka kembali menjadi draft langsung hilang dari portal', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);
    $absensi = catatKehadiranOrangTua($anak, '2026-09-15', StatusSesiAbsensiSiswa::Final, StatusKehadiranSiswa::Hadir);

    $this->actingAs($orangTua)->get(route('orang-tua.dashboard'))
        ->assertInertia(fn ($page) => $page->has('riwayat', 1));

    $absensi->sesi()->update(['status' => StatusSesiAbsensiSiswa::Draft]);

    $this->actingAs($orangTua)->get(route('orang-tua.dashboard'))
        ->assertInertia(fn ($page) => $page->has('riwayat', 0));
});
