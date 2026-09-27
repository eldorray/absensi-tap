<?php

use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\TipeIzin;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\Izin;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // 2026-09-07 Senin. 1-5 September Selasa-Sabtu, 6 September Minggu.
    Carbon::setTestNow('2026-09-07 07:00:00');
});

test('menu absensi tidak lagi mengirim riwayat', function () {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->missing('riwayat')
        );
});

test('riwayat bulan berjalan memuat hari alfa, izin, dan hadir milik guru sendiri', function () {
    [$guru] = guruSiapAbsen();
    $attempt = AbsensiAttempt::factory()->for($guru)->create([
        'tipe' => TipeTap::Masuk, 'hasil' => HasilTap::Diterima, 'created_at' => '2026-09-03 06:50:00',
    ]);
    Absensi::factory()->for($guru)->create([
        'tanggal' => '2026-09-03', 'status' => StatusAbsensi::Hadir, 'masuk_attempt_id' => $attempt->id,
    ]);
    Izin::factory()->for($guru)->disetujui()->create([
        'tipe' => TipeIzin::Izin, 'tanggal_mulai' => '2026-09-04', 'tanggal_selesai' => '2026-09-04',
    ]);
    Absensi::factory()->for(User::factory())->create(['tanggal' => '2026-09-05']);

    $this->actingAs($guru)
        ->get(route('riwayat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('riwayat/Index')
            ->where('bulan.nilai', '2026-09')
            ->where('bulan.label', 'September 2026')
            ->where('bulan.sebelumnya', '2026-08')
            ->where('bulan.berikutnya', null)
            // Minggu dilewati, hari sesudah hari ini tidak ikut: 7,5,4,3,2,1.
            ->has('hari', 6)
            ->where('hari.0.tanggal', '2026-09-07')
            ->where('hari.0.status', 'belum')
            ->where('hari.1.tanggal', '2026-09-05')
            ->where('hari.1.status', 'alfa')
            ->where('hari.2.status', 'izin')
            ->where('hari.3.status', 'hadir')
            ->where('hari.3.jam_masuk', '06:50')
            ->where('ringkasan.hari_efektif', 4)
            ->where('ringkasan.hadir', 1)
            ->where('ringkasan.izin', 1)
            ->where('ringkasan.alfa', 3)
            ->where('ringkasan.persentase', 25)
        );
});

test('riwayat bisa membuka bulan sebelumnya lewat parameter bulan', function () {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('riwayat.index', ['bulan' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bulan.nilai', '2026-08')
            ->where('bulan.sebelumnya', '2026-07')
            ->where('bulan.berikutnya', '2026-09')
            // Agustus 2026: 31 hari dikurangi 5 hari Minggu.
            ->has('hari', 26)
            ->where('hari.0.tanggal', '2026-08-31')
        );
});

test('riwayat menolak bulan yang belum berjalan dan format rusak', function (string $bulan) {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('riwayat.index', ['bulan' => $bulan]))
        ->assertSessionHasErrors('bulan');
})->with(['bulan depan' => '2026-10', 'format rusak' => 'kemarin']);

test('admin yang ikut absen melihat riwayatnya sendiri', function () {
    app(JadwalKerjaSeeder::class)->run();
    $admin = User::factory()->admin()->create();
    Absensi::factory()->for($admin)->create(['tanggal' => '2026-09-03']);

    $this->actingAs($admin)
        ->get(route('riwayat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('hari', 6)
            ->where('hari.3.status', 'hadir')
            ->where('ringkasan.hadir', 1)
        );
});

test('tanda tanpa biometrik hanya untuk pengguna yang memasang passkey', function (bool $punyaPasskey) {
    [$guru] = guruSiapAbsen();
    $attempt = AbsensiAttempt::factory()->for($guru)->create([
        'tipe' => TipeTap::Masuk, 'hasil' => HasilTap::Diterima, 'terverifikasi' => false,
        'created_at' => '2026-09-03 06:50:00',
    ]);
    Absensi::factory()->for($guru)->create(['tanggal' => '2026-09-03', 'masuk_attempt_id' => $attempt->id]);

    if ($punyaPasskey) {
        pasangPasskeyPalsu($guru);
    }

    $this->actingAs($guru)
        ->get(route('riwayat.index'))
        ->assertInertia(fn ($page) => $page
            ->where('hari.3.tanggal', '2026-09-03')
            ->where('hari.3.tanpa_biometrik', $punyaPasskey)
        );
})->with(['tanpa passkey' => false, 'dengan passkey' => true]);

test('tamu tidak dapat membuka riwayat absensi', function () {
    $this->get(route('riwayat.index'))->assertRedirect(route('login'));
});
