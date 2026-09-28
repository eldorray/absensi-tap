<?php

use App\Models\Siswa;
use App\Models\User;
use App\Notifications\IzinDiajukan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;
use Minishlink\WebPush\VAPID;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
    Carbon::setTestNow('2026-09-16 08:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
 */
function langgananBrowser(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'kunci-publik', 'auth' => 'token-auth']];
}

test('admin menyimpan langganan push perangkatnya', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.langganan-push.store'), langgananBrowser())
        ->assertRedirect();

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $admin->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'public_key' => 'kunci-publik',
        'auth_token' => 'token-auth',
    ]);
});

test('guru tidak bisa berlangganan push admin', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.langganan-push.store'), langgananBrowser())
        ->assertForbidden();

    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('endpoint push selain https ditolak', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.langganan-push.store'), langgananBrowser('http://10.0.0.1/internal'))
        ->assertSessionHasErrors('endpoint');

    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('admin mematikan langganan push perangkatnya', function () {
    $admin = User::factory()->admin()->create();
    $admin->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'kunci-publik', 'token-auth');

    $this->actingAs($admin)
        ->delete(route('admin.langganan-push.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])
        ->assertRedirect();

    $this->assertDatabaseCount('push_subscriptions', 0);
});

test('izin guru baru dikirim ke admin aktif yang berlangganan saja', function () {
    Notification::fake();

    $adminBerlangganan = User::factory()->admin()->create();
    $adminBerlangganan->updatePushSubscription('https://fcm.googleapis.com/fcm/send/a');
    $adminTanpaLangganan = User::factory()->admin()->create();
    $adminNonaktif = User::factory()->admin()->create(['is_active' => false]);
    $adminNonaktif->updatePushSubscription('https://fcm.googleapis.com/fcm/send/b');
    $guru = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-18',
        'alasan' => 'Demam tinggi.',
    ])->assertRedirect(route('izin.index'));

    Notification::assertSentTo(
        $adminBerlangganan,
        IzinDiajukan::class,
        fn (IzinDiajukan $notifikasi): bool => $notifikasi->toWebPush($adminBerlangganan, $notifikasi)->toArray() === [
            'title' => 'Izin guru: Budi Santoso',
            'body' => 'Sakit, 17 September - 18 September 2026',
            'icon' => '/pwa-192.png',
            'data' => ['url' => route('admin.izin.index')],
        ],
    );
    Notification::assertNotSentTo([$adminTanpaLangganan, $adminNonaktif, $guru], IzinDiajukan::class);
});

test('izin dari orang tua dikirim ke admin yang berlangganan', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $admin->updatePushSubscription('https://fcm.googleapis.com/fcm/send/a');
    $orangTua = User::factory()->orangTua()->create(['name' => 'Ibu Sari']);
    $anak = Siswa::factory()->create(['nama' => 'Aisyah Putri']);
    $orangTua->siswas()->attach($anak);

    $this->actingAs($orangTua)->post(route('orang-tua.izin.store'), [
        'siswa_id' => $anak->id,
        'tipe' => 'izin',
        'tanggal_mulai' => '2026-09-17',
        'tanggal_selesai' => '2026-09-17',
        'alasan' => 'Ada keperluan keluarga.',
    ])->assertRedirect(route('orang-tua.izin.index'));

    Notification::assertSentTo(
        $admin,
        IzinDiajukan::class,
        fn (IzinDiajukan $notifikasi): bool => $notifikasi->judul === 'Izin siswa: Aisyah Putri'
            && $notifikasi->isi === 'Izin, 17 September 2026 (oleh Ibu Sari)'
            && $notifikasi->url === route('admin.izin-orang-tua.index'),
    );
});

/**
 * Kunci VAPID asli dan langganan dengan kunci p256dh yang sah, supaya
 * enkripsi payload benar-benar berjalan sampai ke permintaan HTTP.
 */
function langgananSah(User $admin, string $endpoint = 'https://web.push.apple.com/QGxhbGFsYQ'): void
{
    $vapid = VAPID::createVapidKeys();
    config(['webpush.vapid.public_key' => $vapid['publicKey'], 'webpush.vapid.private_key' => $vapid['privateKey']]);

    $ec = openssl_pkey_get_details(openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]))['ec'];
    $base64url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    $admin->updatePushSubscription(
        $endpoint,
        $base64url("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
        $base64url(random_bytes(16)),
    );
}

test('kirim tes melaporkan push yang diterima layanan push', function () {
    Http::preventStrayRequests();
    Http::fake(['web.push.apple.com/*' => Http::response('', 201)]);
    $admin = User::factory()->admin()->create();
    langgananSah($admin);

    $this->actingAs($admin)->post(route('admin.langganan-push.tes'))->assertRedirect();

    expect(session(SessionKey::FLASH_DATA)['toast'])->toBe([
        'type' => 'success',
        'message' => 'Diterima layanan push untuk 1 perangkat. Kalau tidak muncul, cek pengaturan notifikasi di HP.',
    ]);
});

test('kirim tes menampilkan dan mencatat alasan penolakan layanan push', function () {
    Http::preventStrayRequests();
    Http::fake(['web.push.apple.com/*' => Http::response('{"reason":"BadJwtToken"}', 403)]);
    Log::spy();
    $admin = User::factory()->admin()->create();
    langgananSah($admin);

    $this->actingAs($admin)->post(route('admin.langganan-push.tes'))->assertRedirect();

    expect(session(SessionKey::FLASH_DATA)['toast'])->toBe([
        'type' => 'error',
        'message' => 'Ditolak layanan push (403): {"reason":"BadJwtToken"}',
    ]);
    Log::shouldHaveReceived('warning')->once()->with('Web push ditolak layanan push.', [
        'user_id' => $admin->id,
        'layanan' => 'web.push.apple.com',
        'status' => 403,
        'alasan' => '{"reason":"BadJwtToken"}',
    ]);
});

test('kirim tes tanpa langganan meminta admin mengaktifkan dulu', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.langganan-push.tes'))
        ->assertRedirect();

    expect(session(SessionKey::FLASH_DATA)['toast'])->toBe([
        'type' => 'error',
        'message' => 'Belum ada perangkat yang berlangganan. Tekan Aktifkan dulu.',
    ]);
});
