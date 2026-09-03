<?php

// tests/Feature/AbsensiTapTest.php

use App\Actions\Absensi\CatatAbsensi;
use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payload tap yang valid, tepat di titik lokasi.
 *
 * @return array<string, mixed>
 */
function payloadTap(Perangkat $perangkat, string $tipe = 'masuk'): array
{
    return [
        'tipe' => $tipe,
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ];
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 07:00:00');
});

test('tap masuk di dalam radius diterima', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $absensi = Absensi::where('user_id', $guru->id)->firstOrFail();

    expect($absensi->status)->toBe(StatusAbsensi::Hadir)
        ->and($absensi->masuk_attempt_id)->not->toBeNull()
        ->and(AbsensiAttempt::where('hasil', HasilTap::Diterima)->count())->toBe(1)
        ->and(AbsensiAttempt::where('hasil', HasilTap::Diterima)->value('terverifikasi'))->toBeFalsy();
});

test('tap di luar radius ditolak dan tidak membuat baris absensi', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    // Sekitar 340 m ke utara dari titik lokasi (0,003 derajat lintang).
    $payload = [...payloadTap($perangkat), 'latitude' => -6.1723];

    $this->actingAs($guru)
        ->post(route('absensi.store'), $payload)
        ->assertSessionHasErrors('tap');

    expect(Absensi::count())->toBe(0)
        ->and(AbsensiAttempt::where('hasil', HasilTap::LuarRadius)->count())->toBe(1)
        ->and(AbsensiAttempt::where('hasil', HasilTap::LuarRadius)->value('jarak_meter'))
        ->toBeGreaterThan(200);
});

test('akurasi GPS buruk ditolak', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->post(route('absensi.store'), [...payloadTap($perangkat), 'accuracy' => 120])
        ->assertSessionHasErrors('tap');

    expect(Absensi::count())->toBe(0)
        ->and(AbsensiAttempt::where('hasil', HasilTap::AkurasiBuruk)->count())->toBe(1);
});

test('perangkat yang tidak terdaftar ditolak', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->post(route('absensi.store'), [...payloadTap($perangkat), 'device_uuid' => (string) Str::uuid()])
        ->assertSessionHasErrors('tap');

    expect(Absensi::count())->toBe(0)
        ->and(AbsensiAttempt::where('hasil', HasilTap::PerangkatAsing)->count())->toBe(1);
});

test('HP guru lain tidak bisa dipakai absen', function () {
    [$guru] = guruSiapAbsen();
    $perangkatOrangLain = Perangkat::factory()->for(User::factory())->create();

    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkatOrangLain))
        ->assertSessionHasErrors('tap');

    expect(Absensi::count())->toBe(0)
        ->and(AbsensiAttempt::where('hasil', HasilTap::PerangkatAsing)->count())->toBe(1);
});

test('perangkat pending belum bisa dipakai absen', function () {
    [$guru] = guruSiapAbsen();
    $pending = Perangkat::factory()->for($guru)->pending()->create();

    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($pending))
        ->assertSessionHasErrors('tap');

    expect(AbsensiAttempt::where('hasil', HasilTap::PerangkatAsing)->count())->toBe(1);
});

test('tap masuk dua kali ditolak sebagai duplikat', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat));
    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertSessionHasErrors('tap');

    expect(AbsensiAttempt::where('hasil', HasilTap::Duplikat)->count())->toBe(1)
        ->and(Absensi::count())->toBe(1);
});

test('tap serentak yang lolos pengecekan duplikat tetap ditolak, jejak audit tidak ikut rollback', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    // Simulasikan race dua tap bersamaan secara deterministik: sisipkan baris
    // "absensis" pesaing tepat sebelum INSERT nyata terjadi -- persis meniru
    // request lain yang menang balapan setelah pengecekan duplikat di atas
    // sudah lolos (row belum ada saat itu). Ini memaksa constraint unique
    // (user_id, tanggal) gagal justru di dalam transaksi, bukan di
    // pengecekan awal.
    Absensi::creating(function (Absensi $absensi) use ($guru): void {
        DB::table('absensis')->insert([
            'user_id' => $guru->id,
            'tanggal' => Carbon::today()->toDateTimeString(),
            'pulang_cepat' => false,
        ]);
    });

    try {
        $this->actingAs($guru)
            ->post(route('absensi.store'), payloadTap($perangkat))
            ->assertSessionHasErrors('tap');

        // Transaksi rollback menghapus baris Absensi (punya kita maupun
        // pesaing) dan baris AbsensiAttempt "Diterima" yang dibuat di
        // dalamnya -- tapi catch block di CatatAbsensi menulis ulang jejak
        // Duplikat DI LUAR transaksi, jadi baris ini harus tetap ada.
        expect(AbsensiAttempt::where('hasil', HasilTap::Duplikat)->count())->toBe(1)
            ->and(AbsensiAttempt::where('hasil', HasilTap::Diterima)->count())->toBe(0);
    } finally {
        // Listener model statis tidak dibersihkan oleh RefreshDatabase --
        // harus dilepas manual supaya tidak bocor ke test lain yang
        // menyimpan Absensi.
        Absensi::flushEventListeners();
    }
});

test('tap pulang tanpa tap masuk ditolak', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkat, 'pulang'))
        ->assertSessionHasErrors('tap');

    expect(AbsensiAttempt::where('hasil', HasilTap::BelumMasuk)->count())->toBe(1);
});

test('tap setelah jam masuk plus toleransi berstatus terlambat', function () {
    Carbon::setTestNow('2026-09-07 07:11:00');

    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat));

    expect(Absensi::where('user_id', $guru->id)->value('status'))->toBe(StatusAbsensi::Terlambat);
});

test('tap tepat di batas toleransi masih hadir', function () {
    Carbon::setTestNow('2026-09-07 07:10:00');

    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat));

    expect(Absensi::where('user_id', $guru->id)->value('status'))->toBe(StatusAbsensi::Hadir);
});

test('tap pulang sebelum jam pulang ditandai pulang cepat', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat));

    Carbon::setTestNow('2026-09-07 12:00:00');
    $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat, 'pulang'));

    $absensi = Absensi::where('user_id', $guru->id)->firstOrFail();

    expect($absensi->pulang_cepat)->toBeTrue()
        ->and($absensi->pulang_attempt_id)->not->toBeNull();
});

test('koordinat di luar rentang bumi ditolak validasi', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->post(route('absensi.store'), [...payloadTap($perangkat), 'latitude' => 91])
        ->assertSessionHasErrors('latitude');
});

test('tap kesebelas dalam satu menit dibatasi', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    foreach (range(1, 10) as $ignored) {
        $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat));
    }

    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertStatus(429);
});

test('guru yang punya passkey wajib verifikasi biometrik sebelum tap', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    pasangPasskeyPalsu($guru);

    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertSessionHasErrors('tap');

    expect(Absensi::count())->toBe(0)
        ->and(AbsensiAttempt::where('hasil', HasilTap::PasskeyInvalid)->count())->toBe(1);
});

test('verifikasi biometrik yang masih segar menandai absensi terverifikasi', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    pasangPasskeyPalsu($guru);

    $this->actingAs($guru)
        ->withSession([CatatAbsensi::KEY_VERIFIKASI => now()->toIso8601String()])
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertSessionHasNoErrors();

    expect(AbsensiAttempt::where('hasil', HasilTap::Diterima)->value('terverifikasi'))->toBeTruthy();
});

test('verifikasi biometrik yang kedaluwarsa tidak dihitung', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    pasangPasskeyPalsu($guru);

    $this->actingAs($guru)
        ->withSession([CatatAbsensi::KEY_VERIFIKASI => now()->subMinutes(5)->toIso8601String()])
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertSessionHasErrors('tap');

    expect(AbsensiAttempt::where('hasil', HasilTap::PasskeyInvalid)->count())->toBe(1);
});

test('penanda verifikasi hanya sekali pakai', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    pasangPasskeyPalsu($guru);

    $this->actingAs($guru)
        ->withSession([CatatAbsensi::KEY_VERIFIKASI => now()->toIso8601String()])
        ->post(route('absensi.store'), payloadTap($perangkat))
        ->assertSessionHasNoErrors();

    Carbon::setTestNow('2026-09-07 12:00:00');

    // Tap pulang tanpa verifikasi baru harus ditolak: penanda sudah dikonsumsi.
    $this->actingAs($guru)
        ->post(route('absensi.store'), payloadTap($perangkat, 'pulang'))
        ->assertSessionHasErrors('tap');
});

test('endpoint passkey options menolak guru tanpa passkey', function () {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)->get(route('absensi.passkey-options'))->assertStatus(409);
});

test('endpoint passkey options mengembalikan options untuk guru yang punya passkey', function () {
    [$guru] = guruSiapAbsen();
    pasangPasskeyPalsu($guru);

    $this->actingAs($guru)
        ->get(route('absensi.passkey-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge']])
        ->assertSessionHas('passkey.verification_options');
});

test('tamu tidak bisa tap', function () {
    [, $perangkat] = guruSiapAbsen();

    $this->post(route('absensi.store'), payloadTap($perangkat))->assertRedirect(route('login'));
});
