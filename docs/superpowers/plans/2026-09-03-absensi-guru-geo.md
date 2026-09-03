# Absensi Guru Berbasis Geolokasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guru tap tombol di HP pribadinya untuk absen masuk/pulang; sistem hanya menerima tap yang benar-benar berada di lokasi sekolah, dari HP yang terikat ke guru itu, dengan verifikasi biometrik bila tersedia — tanpa selfie.

**Architecture:** Laravel monolith + Inertia + Svelte 5 sebagai PWA standalone. Geofence dihitung **di server** (haversine) — klien hanya mengirim koordinat mentah. Anti-titip bertumpu dua lapis independen: `perangkats.uuid` unique **global** (membuktikan perangkat) dan passkey WebAuthn `userVerification: required` (membuktikan orang). Setiap tap, diterima maupun ditolak, ditulis ke `absensi_attempts` sebagai jejak audit. Logika bisnis di Action class; status harian dihitung fungsi murni yang bisa diuji tanpa DB.

**Tech Stack:** Laravel, Fortify, `laravel/passkeys` (dibungkus Fortify), Inertia v3, Svelte 5, Tailwind 4, shadcn-svelte, Wayfinder, SQLite, Pest, Pint, PHPStan level 7.

**Spec:** `docs/superpowers/specs/2026-09-03-absensi-guru-geo-design.md`

## Global Constraints

Setiap task secara implisit terikat aturan di bawah ini.

- **Timezone `Asia/Jakarta`.** Task 1 menyetelnya. Semua perbandingan jam absen memakai `now()`/`today()` tanpa konversi manual.
- **Produksi wajib HTTPS.** `navigator.geolocation` mati di HTTP non-localhost.
- **Tanpa dependency baru.** Satu-satunya penambahan: `npx shadcn-svelte@latest add table` (generator resmi, bukan dependency).
- **`declare(strict_types=1)` TIDAK dipakai** di `app/`. Alasan: tidak ada satu pun file di `app/` yang memakainya (lihat `app/Models/User.php`, `app/Http/Controllers/Settings/ProfileController.php`). Ikuti repo, jangan campur dua gaya.
- **Model pakai atribut PHP, bukan properti.** `#[Fillable([...])]` dan `#[Hidden([...])]`, seperti `app/Models/User.php`. `$fillable` selalu eksplisit — tidak pernah `$guarded = []`, tidak pernah `Model::create($request->all())`.
- **Tipe eksplisit** di semua parameter, return type, dan closure. PHPDoc array shape untuk array.
- **Kolom "enum" di DB adalah `string`**, dengan PHP enum di `casts()`. Alasan: portabel MySQL/SQLite dan bisa ditambah nilai tanpa `ALTER`. Jaminan tetap ada di lapisan aplikasi.
- **Enum keys TitleCase**: `case Guru`, `case LuarRadius`.
- **Otorisasi selalu di server.** Route admin pakai `can:admin`. Prop Inertia hanya menyembunyikan tombol.
- **UI ikut yang sudah ada.** Token `--g-*`, kelas `.g-tile`, `.g-tone-blue|green|yellow|red|plain`, `.g-display`, komponen `resources/js/components/ui/`, `AppHead`, breadcrumbs lewat `export const layout` di `<script module>`, toast lewat `Inertia::flash('toast', ['type' => ..., 'message' => ...])`.
- **Route dipanggil dari frontend lewat Wayfinder.** Setelah menambah route: `php artisan wayfinder:generate`. Jangan hardcode URL di Svelte.
- **Perintah kualitas, dijalankan sebelum tiap commit:**
  - `vendor/bin/pint --dirty --format agent`
  - `vendor/bin/phpstan analyse`
  - `php artisan test --compact`
- **Test Feature otomatis dapat `RefreshDatabase`** (`tests/Pest.php` menerapkannya `->in('Feature')`). Test **Unit tidak mem-boot aplikasi** — jangan panggil `config()`, `now()`, atau Eloquent di sana.
- **Tidak ada antrean tap offline. Permanen.** `public/sw.js` hanya menangani `GET`. Jangan diubah ke background sync.

---

## File Structure

**Dibuat — PHP**

| File | Tanggung jawab |
|---|---|
| `app/Enums/Role.php` | `Guru`, `Admin` |
| `app/Enums/StatusHari.php` | Hasil akhir status harian untuk rekap |
| `app/Enums/StatusAbsensi.php` | `Hadir`, `Terlambat` |
| `app/Enums/TipeTap.php` | `Masuk`, `Pulang` |
| `app/Enums/HasilTap.php` | Hasil satu percobaan tap |
| `app/Enums/StatusPerangkat.php` | `Pending`, `Active`, `Revoked` |
| `app/Enums/TipeIzin.php` | `Izin`, `Sakit`, `Cuti` |
| `app/Enums/StatusIzin.php` | `Pending`, `Disetujui`, `Ditolak` |
| `app/Support/Jarak.php` | Haversine. Fungsi murni. |
| `app/Support/StatusHarian.php` | Aturan status harian. Fungsi murni. |
| `app/Models/Lokasi.php` `JadwalKerja.php` `HariLibur.php` | Master data |
| `app/Models/Perangkat.php` | Device binding |
| `app/Models/AbsensiAttempt.php` `Absensi.php` `Izin.php` | Catatan transaksional |
| `app/Actions/Absensi/DaftarkanPerangkat.php` | Ikat HP ke guru |
| `app/Actions/Absensi/CatatAbsensi.php` | Semua gerbang penolak + tulis absensi |
| `app/Actions/Absensi/RekapBulanan.php` | Susun rekap satu bulan |
| `app/Http/Controllers/AbsensiController.php` | Dashboard + `store` tap |
| `app/Http/Controllers/AbsensiPasskeyController.php` | Options WebAuthn untuk step-up |
| `app/Http/Controllers/PerangkatController.php` | Pendaftaran HP oleh guru |
| `app/Http/Controllers/IzinController.php` | Izin milik guru sendiri |
| `app/Http/Controllers/Admin/IzinController.php` | Approve/reject |
| `app/Http/Controllers/Admin/PengaturanController.php` | Lokasi + jadwal + hari libur, satu halaman |
| `app/Http/Controllers/Admin/GuruController.php` | CRUD guru + approve perangkat |
| `app/Http/Controllers/Admin/RekapController.php` | Rekap + export CSV |
| `app/Http/Requests/*` | Satu FormRequest per aksi yang menerima input |
| `app/Policies/IzinPolicy.php` | Guru hanya melihat izinnya sendiri |

**Dibuat — frontend**

| File | Tanggung jawab |
|---|---|
| `resources/js/pages/Dashboard.svelte` (rombak) | Kartu status + tombol TAP |
| `resources/js/components/TapButton.svelte` | Geolocation + passkey + POST |
| `resources/js/components/InstallPrompt.svelte` | Banner install Android + instruksi iOS |
| `resources/js/lib/perangkat.ts` | Baca/tulis `device_uuid` |
| `resources/js/pages/izin/Index.svelte` | Daftar + form izin guru |
| `resources/js/pages/admin/{Izin,Pengaturan,Guru,Rekap}.svelte` | Empat halaman admin |
| `resources/js/components/ui/table/*` | Dari generator shadcn-svelte |

**Dimodifikasi**

`config/app.php` (timezone) · `app/Models/User.php` · `database/factories/UserFactory.php` · `app/Providers/AppServiceProvider.php` (Gate) · `routes/web.php` · `resources/views/app.blade.php` (meta PWA) · `resources/css/app.css` (safe-area) · `public/manifest.webmanifest` · `resources/js/components/AppSidebar.svelte` · `resources/js/pages/settings/Security.svelte` · `tests/Pest.php` (helper) · `database/seeders/DatabaseSeeder.php`

---

## Task 1: Fondasi — timezone, kolom `users`, gate admin

**Files:**
- Modify: `config/app.php:68`
- Create: `app/Enums/Role.php`
- Create: `database/migrations/<timestamp>_add_absensi_columns_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/FondasiTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\Role` (`Role::Guru`, `Role::Admin`); `User::$role` (cast `Role`), `User::$nip` (`?string`), `User::$is_active` (`bool`); `User::factory()->admin()`; `Gate::allows('admin')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/FondasiTest.php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('aplikasi memakai waktu Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Jakarta');
});

test('guru baru default role guru dan aktif', function () {
    $guru = User::factory()->create();

    expect($guru->role)->toBe(Role::Guru)
        ->and($guru->is_active)->toBeTrue()
        ->and($guru->nip)->toBeNull();
});

test('guru tidak lolos gate admin', function () {
    expect(Gate::forUser(User::factory()->create())->allows('admin'))->toBeFalse();
});

test('admin lolos gate admin', function () {
    expect(Gate::forUser(User::factory()->admin()->create())->allows('admin'))->toBeTrue();
});

test('admin nonaktif tidak lolos gate admin', function () {
    $admin = User::factory()->admin()->create(['is_active' => false]);

    expect(Gate::forUser($admin)->allows('admin'))->toBeFalse();
});

test('role dan is_active tidak bisa diisi mass assignment', function () {
    $guru = new User;
    $guru->fill(['name' => 'Bu Aminah', 'email' => 'aminah@example.test', 'role' => 'admin', 'is_active' => false]);

    expect($guru->role)->toBeNull()
        ->and($guru->is_active)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/FondasiTest.php`
Expected: FAIL — `App\Enums\Role` tidak ada, `config('app.timezone')` masih `UTC`.

- [ ] **Step 3: Set timezone**

`config/app.php` baris 68:

```php
    'timezone' => 'Asia/Jakarta',
```

- [ ] **Step 4: Create the Role enum**

Run: `php artisan make:enum Role --string --no-interaction`

```php
<?php

namespace App\Enums;

enum Role: string
{
    case Guru = 'guru';
    case Admin = 'admin';
}
```

Kalau `make:enum` tidak tersedia di versi Artisan ini, buat file itu langsung dengan isi yang sama.

- [ ] **Step 5: Create the migration**

Run: `php artisan make:migration add_absensi_columns_to_users_table --no-interaction`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nip')->nullable()->after('name');
            $table->string('role')->default('guru')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['nip', 'role', 'is_active']);
        });
    }
};
```

- [ ] **Step 6: Update the User model**

Di `app/Models/User.php`: tambah `nip` ke atribut `#[Fillable]`, **jangan** tambah `role` atau `is_active` (dikelola admin lewat penugasan eksplisit, bukan mass assignment). Tambah PHPDoc property dan casts.

```php
// tambahkan ke blok PHPDoc di atas class:
 * @property string|null $nip
 * @property Role $role
 * @property bool $is_active

// ganti atribut Fillable:
#[Fillable(['name', 'nip', 'email', 'password'])]

// tambahkan import:
use App\Enums\Role;

// di dalam casts():
            'role' => Role::class,
            'is_active' => 'boolean',
```

- [ ] **Step 7: Add the admin factory state**

Di `database/factories/UserFactory.php`, tambah setelah `unverified()`:

```php
    /**
     * Indicate that the user administers the attendance system.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => Role::Admin,
        ]);
    }
```

Tambah `use App\Enums\Role;` di bagian import.

- [ ] **Step 8: Define the admin gate**

Di `app/Providers/AppServiceProvider.php`, dalam `boot()`:

```php
        Gate::define('admin', fn (User $user): bool => $user->role === Role::Admin && $user->is_active);
```

Import: `use App\Enums\Role;`, `use App\Models\User;`, `use Illuminate\Support\Facades\Gate;`.

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/FondasiTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 10: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: Jakarta timezone, user role columns, admin gate"
```

---

## Task 2: `App\Support\Jarak` — haversine

**Files:**
- Create: `app/Support/Jarak.php`
- Test: `tests/Unit/JarakTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Jarak::meter(float $lat1, float $lng1, float $lat2, float $lng2): int` — jarak lingkaran besar dalam meter, dibulatkan.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Unit/JarakTest.php

use App\Support\Jarak;

test('titik yang sama berjarak nol meter', function () {
    expect(Jarak::meter(-6.2088, 106.8456, -6.2088, 106.8456))->toBe(0);
});

test('satu derajat lintang kurang lebih 111 kilometer', function () {
    expect(Jarak::meter(0.0, 0.0, 1.0, 0.0))
        ->toBeGreaterThan(111_100)
        ->toBeLessThan(111_400);
});

test('Monas ke Kota Tua kurang lebih 4,7 kilometer', function () {
    expect(Jarak::meter(-6.175392, 106.827153, -6.135200, 106.813309))
        ->toBeGreaterThan(4_300)
        ->toBeLessThan(4_800);
});

test('titik antipodal tidak memicu NaN', function () {
    expect(Jarak::meter(0.0, 0.0, 0.0, 180.0))->toBeGreaterThan(20_000_000);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/JarakTest.php`
Expected: FAIL — `Class "App\Support\Jarak" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Support;

final class Jarak
{
    /**
     * Radius bumi rata-rata dalam meter (IUGG mean radius).
     */
    private const RADIUS_BUMI_METER = 6_371_000.0;

    /**
     * Jarak lingkaran besar antara dua koordinat, dibulatkan ke meter.
     */
    public static function meter(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        // min(1.0, ...) menjaga asin() dari galat pembulatan pada titik antipodal.
        return (int) round(self::RADIUS_BUMI_METER * 2 * asin(min(1.0, sqrt($a))));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/JarakTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Support/Jarak.php tests/Unit/JarakTest.php
git commit -m "feat: haversine distance helper"
```

---

## Task 3: `App\Support\StatusHarian` — aturan status harian

Ini jantung kebenaran rekap. Lima cabang spec, plus satu cabang `Belum` untuk tanggal yang belum lewat (spec tidak menyebutnya; rekap bulan berjalan butuh nilai untuk tanggal depan, dan menyebutnya `Alfa` akan salah).

**Files:**
- Create: `app/Enums/StatusHari.php`
- Create: `app/Support/StatusHarian.php`
- Test: `tests/Unit/StatusHarianTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `App\Enums\StatusHari` dengan case `BukanHariKerja`, `Libur`, `Izin`, `Sakit`, `Cuti`, `Hadir`, `Terlambat`, `Alfa`, `Belum`; method `label(): string`.
  - `StatusHarian::resolve(bool $isHariKerja, bool $isLibur, ?string $tipeIzinDisetujui, ?string $statusAbsensi, bool $tanggalSudahLewat): StatusHari`.
  - Nilai `$tipeIzinDisetujui` yang sah: `'izin'`, `'sakit'`, `'cuti'`. Nilai `$statusAbsensi` yang sah: `'hadir'`, `'terlambat'`. Keduanya sengaja sama dengan `value` di `StatusHari` supaya `StatusHari::from()` langsung memetakannya.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Unit/StatusHarianTest.php

use App\Enums\StatusHari;
use App\Support\StatusHarian;

test('bukan hari kerja mengalahkan semua cabang lain', function () {
    expect(StatusHarian::resolve(false, true, 'sakit', 'hadir', true))
        ->toBe(StatusHari::BukanHariKerja);
});

test('hari libur mengalahkan izin dan absensi', function () {
    expect(StatusHarian::resolve(true, true, 'sakit', 'hadir', true))
        ->toBe(StatusHari::Libur);
});

test('izin disetujui mengalahkan absensi', function () {
    expect(StatusHarian::resolve(true, false, 'sakit', 'hadir', true))
        ->toBe(StatusHari::Sakit);
});

test('absensi hadir dipakai kalau tidak ada izin', function () {
    expect(StatusHarian::resolve(true, false, null, 'hadir', true))
        ->toBe(StatusHari::Hadir);
});

test('absensi terlambat dipakai kalau tidak ada izin', function () {
    expect(StatusHarian::resolve(true, false, null, 'terlambat', true))
        ->toBe(StatusHari::Terlambat);
});

test('hari kerja yang sudah lewat tanpa jejak apa pun adalah alfa', function () {
    expect(StatusHarian::resolve(true, false, null, null, true))
        ->toBe(StatusHari::Alfa);
});

test('hari kerja yang belum lewat tanpa jejak apa pun belum berstatus', function () {
    expect(StatusHarian::resolve(true, false, null, null, false))
        ->toBe(StatusHari::Belum);
});

test('setiap status punya label untuk ditampilkan', function () {
    foreach (StatusHari::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/StatusHarianTest.php`
Expected: FAIL — `Class "App\Enums\StatusHari" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum StatusHari: string
{
    case BukanHariKerja = 'bukan_hari_kerja';
    case Libur = 'libur';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Alfa = 'alfa';
    case Belum = 'belum';

    /**
     * Label singkat untuk tabel rekap dan export CSV.
     */
    public function label(): string
    {
        return match ($this) {
            self::BukanHariKerja => '-',
            self::Libur => 'Libur',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Alfa => 'Alfa',
            self::Belum => 'Belum',
        };
    }
}
```

- [ ] **Step 4: Write the resolver**

```php
<?php

namespace App\Support;

use App\Enums\StatusHari;

final class StatusHarian
{
    /**
     * Tentukan status satu guru pada satu tanggal.
     *
     * Cabang dievaluasi berurutan dan berhenti di kecocokan pertama. Urutan ini
     * yang menentukan benar-tidaknya seluruh rekap, jadi jangan diubah tanpa
     * memperbarui test-nya.
     *
     * @param  string|null  $tipeIzinDisetujui  'izin', 'sakit', atau 'cuti'
     * @param  string|null  $statusAbsensi  'hadir' atau 'terlambat'
     */
    public static function resolve(
        bool $isHariKerja,
        bool $isLibur,
        ?string $tipeIzinDisetujui,
        ?string $statusAbsensi,
        bool $tanggalSudahLewat,
    ): StatusHari {
        if (! $isHariKerja) {
            return StatusHari::BukanHariKerja;
        }

        if ($isLibur) {
            return StatusHari::Libur;
        }

        if ($tipeIzinDisetujui !== null) {
            return StatusHari::from($tipeIzinDisetujui);
        }

        if ($statusAbsensi !== null) {
            return StatusHari::from($statusAbsensi);
        }

        return $tanggalSudahLewat ? StatusHari::Alfa : StatusHari::Belum;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/StatusHarianTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 6: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add app/Enums/StatusHari.php app/Support/StatusHarian.php tests/Unit/StatusHarianTest.php
git commit -m "feat: daily attendance status resolution rules"
```

---
## Task 4: Master data — lokasi, jadwal kerja, hari libur

Nama tabel di-set eksplisit lewat `protected $table` di setiap model domain. Alasan: pluralisasi otomatis Laravel memakai inflector Inggris dan tidak bisa dipercaya untuk kata Indonesia (`lokasi`, `absensi`). Jangan mengandalkan tebakan.

**Files:**
- Create: `app/Models/Lokasi.php`, `app/Models/JadwalKerja.php`, `app/Models/HariLibur.php`
- Create: `database/migrations/<timestamp>_create_lokasis_table.php`, `..._create_jadwal_kerjas_table.php`, `..._create_hari_liburs_table.php`
- Create: `database/factories/LokasiFactory.php`, `JadwalKerjaFactory.php`, `HariLiburFactory.php`
- Create: `database/seeders/JadwalKerjaSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/MasterDataTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Lokasi` — `$nama` string, `$latitude` float, `$longitude` float, `$radius_meter` int, `$is_active` bool. Tabel `lokasis`.
  - `JadwalKerja` — `$day_of_week` int (0 = Minggu, sesuai `Carbon::dayOfWeek`), `$jam_masuk` string `H:i:s`, `$jam_pulang` string `H:i:s`, `$toleransi_menit` int, `$is_hari_kerja` bool. Tabel `jadwal_kerjas`.
  - `HariLibur` — `$tanggal` `Carbon`, `$nama` string. Tabel `hari_liburs`.
  - `JadwalKerjaSeeder` — mengisi tujuh baris, idempotent lewat `updateOrCreate`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/MasterDataTest.php

use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Database\QueryException;

test('seeder mengisi tujuh hari dengan Minggu bukan hari kerja', function () {
    $this->seed(JadwalKerjaSeeder::class);

    expect(JadwalKerja::count())->toBe(7)
        ->and(JadwalKerja::where('day_of_week', 0)->value('is_hari_kerja'))->toBeFalsy()
        ->and(JadwalKerja::where('day_of_week', 1)->value('is_hari_kerja'))->toBeTruthy()
        ->and(JadwalKerja::where('day_of_week', 1)->value('jam_masuk'))->toBe('07:00:00')
        ->and(JadwalKerja::where('day_of_week', 1)->value('toleransi_menit'))->toBe(10);
});

test('seeder bisa dijalankan dua kali tanpa menduplikasi', function () {
    $this->seed(JadwalKerjaSeeder::class);
    $this->seed(JadwalKerjaSeeder::class);

    expect(JadwalKerja::count())->toBe(7);
});

test('lokasi menyimpan koordinat sebagai float dengan tujuh desimal', function () {
    $lokasi = Lokasi::factory()->create([
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
    ]);

    expect($lokasi->fresh()->latitude)->toBeFloat()->toBe(-6.1753924)
        ->and($lokasi->fresh()->longitude)->toBeFloat()->toBe(106.8271528)
        ->and($lokasi->radius_meter)->toBe(100)
        ->and($lokasi->is_active)->toBeTrue();
});

test('satu tanggal hanya boleh punya satu hari libur', function () {
    HariLibur::factory()->create(['tanggal' => '2026-08-17']);

    expect(fn () => HariLibur::factory()->create(['tanggal' => '2026-08-17']))
        ->toThrow(QueryException::class);
});

test('hari libur mengembalikan tanggal sebagai Carbon', function () {
    $libur = HariLibur::factory()->create(['tanggal' => '2026-08-17', 'nama' => 'HUT RI']);

    expect($libur->fresh()->tanggal->toDateString())->toBe('2026-08-17');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/MasterDataTest.php`
Expected: FAIL — `Class "App\Models\Lokasi" not found`.

- [ ] **Step 3: Generate the models, migrations, and factories**

```bash
php artisan make:model Lokasi --migration --factory --no-interaction
php artisan make:model JadwalKerja --migration --factory --no-interaction
php artisan make:model HariLibur --migration --factory --no-interaction
```

Kalau nama file migration hasil generator bukan `create_lokasis_table` / `create_jadwal_kerjas_table` / `create_hari_liburs_table`, ganti nama filenya agar cocok.

- [ ] **Step 4: Write the migrations**

`create_lokasis_table`:

```php
        Schema::create('lokasis', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meter')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
```

`create_jadwal_kerjas_table`:

```php
        Schema::create('jadwal_kerjas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->unsignedSmallInteger('toleransi_menit')->default(10);
            $table->boolean('is_hari_kerja')->default(true);
            $table->timestamps();
        });
```

`create_hari_liburs_table`:

```php
        Schema::create('hari_liburs', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('nama');
            $table->timestamps();
        });
```

- [ ] **Step 5: Write the models**

```php
<?php

// app/Models/Lokasi.php

namespace App\Models;

use Database\Factories\LokasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meter
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'latitude', 'longitude', 'radius_meter', 'is_active'])]
class Lokasi extends Model
{
    /** @use HasFactory<LokasiFactory> */
    use HasFactory;

    protected $table = 'lokasis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
```

```php
<?php

// app/Models/JadwalKerja.php

namespace App\Models;

use Database\Factories\JadwalKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $day_of_week 0 = Minggu, sesuai Carbon::dayOfWeek
 * @property string $jam_masuk
 * @property string $jam_pulang
 * @property int $toleransi_menit
 * @property bool $is_hari_kerja
 */
#[Fillable(['day_of_week', 'jam_masuk', 'jam_pulang', 'toleransi_menit', 'is_hari_kerja'])]
class JadwalKerja extends Model
{
    /** @use HasFactory<JadwalKerjaFactory> */
    use HasFactory;

    protected $table = 'jadwal_kerjas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'toleransi_menit' => 'integer',
            'is_hari_kerja' => 'boolean',
        ];
    }
}
```

```php
<?php

// app/Models/HariLibur.php

namespace App\Models;

use Database\Factories\HariLiburFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $tanggal
 * @property string $nama
 */
#[Fillable(['tanggal', 'nama'])]
class HariLibur extends Model
{
    /** @use HasFactory<HariLiburFactory> */
    use HasFactory;

    protected $table = 'hari_liburs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
```

- [ ] **Step 6: Write the factories**

```php
<?php

// database/factories/LokasiFactory.php — isi definition()

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'Gerbang Utama',
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'radius_meter' => 100,
            'is_active' => true,
        ];
    }
```

```php
<?php

// database/factories/JadwalKerjaFactory.php — isi definition()

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->unique()->numberBetween(0, 6),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '14:00:00',
            'toleransi_menit' => 10,
            'is_hari_kerja' => true,
        ];
    }
```

```php
<?php

// database/factories/HariLiburFactory.php — isi definition()

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tanggal' => fake()->unique()->date(),
            'nama' => 'Cuti Bersama',
        ];
    }
```

- [ ] **Step 7: Write the seeder**

Run: `php artisan make:seeder JadwalKerjaSeeder --no-interaction`

```php
<?php

namespace Database\Seeders;

use App\Models\JadwalKerja;
use Illuminate\Database\Seeder;

class JadwalKerjaSeeder extends Seeder
{
    /**
     * Senin sampai Sabtu 07:00-14:00 dengan toleransi 10 menit; Minggu bukan hari kerja.
     *
     * Dibuat idempotent supaya aman dijalankan ulang di server yang sudah berjalan.
     */
    public function run(): void
    {
        foreach (range(0, 6) as $day) {
            JadwalKerja::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'jam_masuk' => '07:00:00',
                    'jam_pulang' => '14:00:00',
                    'toleransi_menit' => 10,
                    'is_hari_kerja' => $day !== 0,
                ],
            );
        }
    }
}
```

Daftarkan di `database/seeders/DatabaseSeeder.php` dalam `run()`:

```php
        $this->call(JadwalKerjaSeeder::class);
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/MasterDataTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 9: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: attendance master data (locations, schedule, holidays)"
```

---

## Task 5: `perangkats` — device binding dengan uuid unique global

Test paling penting di seluruh suite ada di task ini: satu HP tidak boleh terdaftar di dua akun. Itu yang mematikan pola titip absen paling umum.

**Files:**
- Create: `app/Enums/StatusPerangkat.php`
- Create: `app/Models/Perangkat.php`
- Create: `database/migrations/<timestamp>_create_perangkats_table.php`
- Create: `database/factories/PerangkatFactory.php`
- Test: `tests/Feature/PerangkatTest.php`

**Interfaces:**
- Consumes: `App\Models\User`.
- Produces:
  - `App\Enums\StatusPerangkat` — `Pending`, `Active`, `Revoked`.
  - `Perangkat` — `$user_id`, `$uuid` (unique global), `$label`, `$user_agent`, `$status` (cast `StatusPerangkat`), `$approved_by`, `$approved_at`. Tabel `perangkats`. Relasi `user(): BelongsTo<User>`.
  - `Perangkat::factory()->pending()` dan `->revoked()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/PerangkatTest.php

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

test('satu HP tidak bisa terdaftar di dua akun', function () {
    $uuid = (string) Str::uuid();

    Perangkat::factory()->for(User::factory())->create(['uuid' => $uuid]);

    expect(fn () => Perangkat::factory()->for(User::factory())->create(['uuid' => $uuid]))
        ->toThrow(QueryException::class);
});

test('perangkat default berstatus active', function () {
    expect(Perangkat::factory()->for(User::factory())->create()->status)
        ->toBe(StatusPerangkat::Active);
});

test('state pending dan revoked tersedia', function () {
    expect(Perangkat::factory()->for(User::factory())->pending()->create()->status)
        ->toBe(StatusPerangkat::Pending)
        ->and(Perangkat::factory()->for(User::factory())->revoked()->create()->status)
        ->toBe(StatusPerangkat::Revoked);
});

test('perangkat terhubung ke pemiliknya', function () {
    $guru = User::factory()->create();
    $perangkat = Perangkat::factory()->for($guru)->create();

    expect($perangkat->user->id)->toBe($guru->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/PerangkatTest.php`
Expected: FAIL — `Class "App\Enums\StatusPerangkat" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum StatusPerangkat: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
}
```

- [ ] **Step 4: Generate and write the migration**

Run: `php artisan make:model Perangkat --migration --factory --no-interaction`

```php
        Schema::create('perangkats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Unique GLOBAL, bukan unique per user. Ini yang menolak satu HP
            // dipakai dua akun -- pola titip absen paling umum.
            $table->string('uuid')->unique();
            $table->string('label');
            $table->text('user_agent')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
```

- [ ] **Step 5: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\StatusPerangkat;
use Database\Factories\PerangkatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string $label
 * @property string|null $user_agent
 * @property StatusPerangkat $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'uuid', 'label', 'user_agent', 'status'])]
class Perangkat extends Model
{
    /** @use HasFactory<PerangkatFactory> */
    use HasFactory;

    protected $table = 'perangkats';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPerangkat::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 6: Write the factory**

```php
<?php

// database/factories/PerangkatFactory.php

namespace Database\Factories;

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Perangkat>
 */
class PerangkatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'label' => 'Android',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14)',
            'status' => StatusPerangkat::Active,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPerangkat::Pending,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPerangkat::Revoked,
        ]);
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/PerangkatTest.php`
Expected: PASS, 4 tests.

Kalau test "dua akun" gagal karena constraint tidak ditegakkan, pastikan foreign key SQLite aktif dan index `unique` benar-benar terbuat: `php artisan migrate:fresh` lalu periksa `php artisan db:table perangkats`.

- [ ] **Step 8: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: device binding table with globally unique uuid"
```

---

## Task 6: Tabel transaksional — `absensi_attempts`, `absensis`, `izins`

**Files:**
- Create: `app/Enums/TipeTap.php`, `HasilTap.php`, `StatusAbsensi.php`, `TipeIzin.php`, `StatusIzin.php`
- Create: `app/Models/AbsensiAttempt.php`, `Absensi.php`, `Izin.php`
- Create: 3 migration + 3 factory
- Test: `tests/Feature/AbsensiModelTest.php`

**Interfaces:**
- Consumes: `User`, `Lokasi`.
- Produces:
  - `TipeTap` — `Masuk`, `Pulang`.
  - `HasilTap` — `Diterima`, `LuarRadius`, `AkurasiBuruk`, `PerangkatAsing`, `Duplikat`, `PasskeyInvalid`, `BelumMasuk`.
    `PasskeyInvalid` dan `BelumMasuk` adalah tambahan di luar spec: spec tidak menyebut `hasil` mana yang dipakai saat verifikasi passkey gagal, dan tidak menentukan perilaku tap pulang tanpa tap masuk. Keduanya dijawab di sini dengan nilai enum sendiri agar audit tidak menyamarkannya sebagai `duplikat`.
  - `StatusAbsensi` — `Hadir`, `Terlambat`. `value` sengaja sama dengan case `StatusHari` yang sepadan (Task 3).
  - `TipeIzin` — `Izin`, `Sakit`, `Cuti`. `value` juga sepadan dengan `StatusHari`.
  - `StatusIzin` — `Pending`, `Disetujui`, `Ditolak`.
  - `AbsensiAttempt` — `$user_id`, `$tipe` (`TipeTap`), `$latitude` float, `$longitude` float, `$accuracy_meter` int, `$jarak_meter` ?int, `$lokasi_id` ?int, `$perangkat_uuid` string, `$terverifikasi` bool, `$hasil` (`HasilTap`), `$created_at`. Tanpa `updated_at`.
  - `Absensi` — `$user_id`, `$tanggal` Carbon, `$masuk_attempt_id` ?int, `$pulang_attempt_id` ?int, `$status` ?`StatusAbsensi`, `$pulang_cepat` bool; unique(`user_id`,`tanggal`); relasi `masukAttempt()`, `pulangAttempt()`, `user()`.
  - `Izin` — `$user_id`, `$tipe` (`TipeIzin`), `$tanggal_mulai` Carbon, `$tanggal_selesai` Carbon, `$alasan` string, `$lampiran_path` ?string, `$status` (`StatusIzin`), `$reviewed_by` ?int, `$reviewed_at` ?Carbon, `$catatan_review` ?string; relasi `user()`, `reviewer()`. Factory state `disetujui()`, `ditolak()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/AbsensiModelTest.php

use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\Izin;
use App\Models\User;
use Illuminate\Database\QueryException;

test('satu guru hanya punya satu baris absensi per tanggal', function () {
    $guru = User::factory()->create();

    Absensi::factory()->for($guru)->create(['tanggal' => '2026-09-01']);

    expect(fn () => Absensi::factory()->for($guru)->create(['tanggal' => '2026-09-01']))
        ->toThrow(QueryException::class);
});

test('attempt menyimpan hasil dan koordinat', function () {
    $attempt = AbsensiAttempt::factory()->for(User::factory())->create([
        'tipe' => TipeTap::Masuk,
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy_meter' => 12,
        'jarak_meter' => 34,
        'hasil' => HasilTap::Diterima,
        'terverifikasi' => true,
    ]);

    $fresh = $attempt->fresh();

    expect($fresh->tipe)->toBe(TipeTap::Masuk)
        ->and($fresh->hasil)->toBe(HasilTap::Diterima)
        ->and($fresh->latitude)->toBe(-6.1753924)
        ->and($fresh->accuracy_meter)->toBe(12)
        ->and($fresh->terverifikasi)->toBeTrue();
});

test('absensi terhubung ke attempt masuk dan pulang', function () {
    $guru = User::factory()->create();
    $masuk = AbsensiAttempt::factory()->for($guru)->create(['tipe' => TipeTap::Masuk]);
    $pulang = AbsensiAttempt::factory()->for($guru)->create(['tipe' => TipeTap::Pulang]);

    $absensi = Absensi::factory()->for($guru)->create([
        'masuk_attempt_id' => $masuk->id,
        'pulang_attempt_id' => $pulang->id,
        'status' => StatusAbsensi::Terlambat,
    ]);

    expect($absensi->masukAttempt->id)->toBe($masuk->id)
        ->and($absensi->pulangAttempt->id)->toBe($pulang->id)
        ->and($absensi->status)->toBe(StatusAbsensi::Terlambat);
});

test('izin default pending dan punya state disetujui', function () {
    expect(Izin::factory()->for(User::factory())->create()->status)->toBe(StatusIzin::Pending)
        ->and(Izin::factory()->for(User::factory())->disetujui()->create()->status)->toBe(StatusIzin::Disetujui);
});

test('izin menyimpan rentang tanggal sebagai Carbon', function () {
    $izin = Izin::factory()->for(User::factory())->create([
        'tipe' => TipeIzin::Sakit,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-03',
    ]);

    $fresh = $izin->fresh();

    expect($fresh->tipe)->toBe(TipeIzin::Sakit)
        ->and($fresh->tanggal_mulai->toDateString())->toBe('2026-09-01')
        ->and($fresh->tanggal_selesai->toDateString())->toBe('2026-09-03');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/AbsensiModelTest.php`
Expected: FAIL — enum dan model belum ada.

- [ ] **Step 3: Write the enums**

```php
<?php

namespace App\Enums;

enum TipeTap: string
{
    case Masuk = 'masuk';
    case Pulang = 'pulang';
}
```

```php
<?php

namespace App\Enums;

enum HasilTap: string
{
    case Diterima = 'diterima';
    case LuarRadius = 'luar_radius';
    case AkurasiBuruk = 'akurasi_buruk';
    case PerangkatAsing = 'perangkat_asing';
    case Duplikat = 'duplikat';
    case PasskeyInvalid = 'passkey_invalid';
    case BelumMasuk = 'belum_masuk';
}
```

```php
<?php

namespace App\Enums;

/**
 * Nilai sengaja sepadan dengan StatusHari agar StatusHarian::resolve()
 * bisa memetakannya langsung lewat StatusHari::from().
 */
enum StatusAbsensi: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
}
```

```php
<?php

namespace App\Enums;

/**
 * Nilai sengaja sepadan dengan StatusHari, alasan sama seperti StatusAbsensi.
 */
enum TipeIzin: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
}
```

```php
<?php

namespace App\Enums;

enum StatusIzin: string
{
    case Pending = 'pending';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
}
```

- [ ] **Step 4: Generate and write the migrations**

```bash
php artisan make:model AbsensiAttempt --migration --factory --no-interaction
php artisan make:model Absensi --migration --factory --no-interaction
php artisan make:model Izin --migration --factory --no-interaction
```

Pastikan nama file migration: `create_absensi_attempts_table`, `create_absensis_table`, `create_izins_table`.

```php
        Schema::create('absensi_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipe');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy_meter');
            $table->unsignedInteger('jarak_meter')->nullable();
            $table->foreignId('lokasi_id')->nullable()->constrained('lokasis')->nullOnDelete();
            $table->string('perangkat_uuid');
            $table->boolean('terverifikasi')->default(false);
            $table->string('hasil');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['hasil', 'created_at']);
            // Mendukung deteksi anomali "koordinat kembar" di rekap.
            $table->index(['latitude', 'longitude']);
        });
```

```php
        Schema::create('absensis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('masuk_attempt_id')->nullable()->constrained('absensi_attempts')->nullOnDelete();
            $table->foreignId('pulang_attempt_id')->nullable()->constrained('absensi_attempts')->nullOnDelete();
            $table->string('status')->nullable();
            $table->boolean('pulang_cepat')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
```

```php
        Schema::create('izins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipe');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('lampiran_path')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('catatan_review')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
```

- [ ] **Step 5: Write the models**

```php
<?php

// app/Models/AbsensiAttempt.php

namespace App\Models;

use App\Enums\HasilTap;
use App\Enums\TipeTap;
use Database\Factories\AbsensiAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property TipeTap $tipe
 * @property float $latitude
 * @property float $longitude
 * @property int $accuracy_meter
 * @property int|null $jarak_meter
 * @property int|null $lokasi_id
 * @property string $perangkat_uuid
 * @property bool $terverifikasi
 * @property HasilTap $hasil
 * @property Carbon|null $created_at
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'tipe', 'latitude', 'longitude', 'accuracy_meter',
    'jarak_meter', 'lokasi_id', 'perangkat_uuid', 'terverifikasi', 'hasil',
])]
class AbsensiAttempt extends Model
{
    /** @use HasFactory<AbsensiAttemptFactory> */
    use HasFactory;

    protected $table = 'absensi_attempts';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => TipeTap::class,
            'hasil' => HasilTap::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meter' => 'integer',
            'jarak_meter' => 'integer',
            'terverifikasi' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

```php
<?php

// app/Models/Absensi.php

namespace App\Models;

use App\Enums\StatusAbsensi;
use Database\Factories\AbsensiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $tanggal
 * @property int|null $masuk_attempt_id
 * @property int|null $pulang_attempt_id
 * @property StatusAbsensi|null $status
 * @property bool $pulang_cepat
 * @property-read User $user
 * @property-read AbsensiAttempt|null $masukAttempt
 * @property-read AbsensiAttempt|null $pulangAttempt
 */
#[Fillable(['user_id', 'tanggal', 'masuk_attempt_id', 'pulang_attempt_id', 'status', 'pulang_cepat'])]
class Absensi extends Model
{
    /** @use HasFactory<AbsensiFactory> */
    use HasFactory;

    protected $table = 'absensis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusAbsensi::class,
            'pulang_cepat' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AbsensiAttempt, $this>
     */
    public function masukAttempt(): BelongsTo
    {
        return $this->belongsTo(AbsensiAttempt::class, 'masuk_attempt_id');
    }

    /**
     * @return BelongsTo<AbsensiAttempt, $this>
     */
    public function pulangAttempt(): BelongsTo
    {
        return $this->belongsTo(AbsensiAttempt::class, 'pulang_attempt_id');
    }
}
```

```php
<?php

// app/Models/Izin.php

namespace App\Models;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use Database\Factories\IzinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property TipeIzin $tipe
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string $alasan
 * @property string|null $lampiran_path
 * @property StatusIzin $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $catatan_review
 * @property-read User $user
 * @property-read User|null $reviewer
 */
#[Fillable(['user_id', 'tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'lampiran_path'])]
class Izin extends Model
{
    /** @use HasFactory<IzinFactory> */
    use HasFactory;

    protected $table = 'izins';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => TipeIzin::class,
            'status' => StatusIzin::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

`status`, `reviewed_by`, `reviewed_at`, dan `catatan_review` **tidak** masuk `#[Fillable]` — hanya admin yang mengubahnya, lewat penugasan eksplisit.

- [ ] **Step 6: Write the factories**

```php
<?php

// database/factories/AbsensiAttemptFactory.php — definition()

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipe' => TipeTap::Masuk,
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'accuracy_meter' => 15,
            'jarak_meter' => 20,
            'perangkat_uuid' => (string) Str::uuid(),
            'terverifikasi' => false,
            'hasil' => HasilTap::Diterima,
        ];
    }
```

```php
<?php

// database/factories/AbsensiFactory.php — definition()

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tanggal' => today()->toDateString(),
            'status' => StatusAbsensi::Hadir,
            'pulang_cepat' => false,
        ];
    }
```

```php
<?php

// database/factories/IzinFactory.php — definition() dan state

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipe' => TipeIzin::Izin,
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_selesai' => today()->toDateString(),
            'alasan' => 'Mengurus administrasi keluarga.',
        ];
    }

    public function disetujui(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusIzin::Disetujui,
            'reviewed_at' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusIzin::Ditolak,
            'reviewed_at' => now(),
        ]);
    }
```

`status` tidak masuk `#[Fillable]`, tapi state factory di atas tetap tersimpan: `Illuminate\Database\Eloquent\Factories\Factory` membungkus pembuatan model dalam `Model::unguarded()` (lihat `Factory.php:525`). Aturan yang sama berlaku untuk `created_at` yang di-override di test.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/AbsensiModelTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 8: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: attendance attempt, daily attendance, and leave models"
```

---
## Task 7: Pendaftaran perangkat — HP pertama aktif, HP berikutnya menunggu approve

**Files:**
- Create: `app/Actions/Absensi/DaftarkanPerangkat.php`
- Create: `app/Http/Requests/DaftarkanPerangkatRequest.php`
- Create: `app/Http/Controllers/PerangkatController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PerangkatPendaftaranTest.php`

**Interfaces:**
- Consumes: `Perangkat`, `StatusPerangkat`, `User`.
- Produces:
  - `DaftarkanPerangkat::__invoke(User $guru, string $uuid, ?string $userAgent): Perangkat` — melempar `ValidationException` pada key `device_uuid` kalau uuid sudah dimiliki guru lain. Idempotent: uuid yang sudah terdaftar untuk guru yang sama dikembalikan apa adanya.
  - `DaftarkanPerangkat::label(?string $userAgent): string`.
  - Route bernama `perangkat.store` (`POST /perangkat`), memasang cookie `perangkat_uuid` selamanya.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/PerangkatPendaftaranTest.php

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Str;

test('HP pertama langsung aktif dan cookie dipasang', function () {
    $guru = User::factory()->create();
    $uuid = (string) Str::uuid();

    $response = $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);

    $response->assertRedirect(route('dashboard'))
        ->assertCookie('perangkat_uuid', $uuid);

    expect(Perangkat::where('uuid', $uuid)->value('status'))->toBe(StatusPerangkat::Active->value);
});

test('HP kedua masuk pending menunggu approve admin', function () {
    $guru = User::factory()->create();
    Perangkat::factory()->for($guru)->create();

    $uuid = (string) Str::uuid();
    $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);

    expect(Perangkat::where('uuid', $uuid)->value('status'))->toBe(StatusPerangkat::Pending->value)
        ->and(Perangkat::where('user_id', $guru->id)->count())->toBe(2);
});

test('HP milik guru lain ditolak', function () {
    $lain = User::factory()->create();
    $perangkat = Perangkat::factory()->for($lain)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('perangkat.store'), ['device_uuid' => $perangkat->uuid])
        ->assertSessionHasErrors('device_uuid');

    expect(Perangkat::where('uuid', $perangkat->uuid)->count())->toBe(1);
});

test('mendaftarkan uuid yang sama dua kali tidak menambah baris', function () {
    $guru = User::factory()->create();
    $uuid = (string) Str::uuid();

    $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);
    $this->actingAs($guru)->post(route('perangkat.store'), ['device_uuid' => $uuid]);

    expect(Perangkat::where('user_id', $guru->id)->count())->toBe(1);
});

test('uuid wajib berformat uuid', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('perangkat.store'), ['device_uuid' => 'bukan-uuid'])
        ->assertSessionHasErrors('device_uuid');
});

test('tamu tidak bisa mendaftarkan perangkat', function () {
    $this->post(route('perangkat.store'), ['device_uuid' => (string) Str::uuid()])
        ->assertRedirect(route('login'));
});

test('label diturunkan dari user agent', function () {
    expect(App\Actions\Absensi\DaftarkanPerangkat::label('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'))->toBe('iPhone')
        ->and(App\Actions\Absensi\DaftarkanPerangkat::label('Mozilla/5.0 (Linux; Android 14)'))->toBe('Android')
        ->and(App\Actions\Absensi\DaftarkanPerangkat::label(null))->toBe('Perangkat lain');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/PerangkatPendaftaranTest.php`
Expected: FAIL — route `perangkat.store` tidak terdefinisi.

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Actions\Absensi;

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DaftarkanPerangkat
{
    /**
     * Ikat satu HP ke seorang guru.
     *
     * HP pertama langsung aktif supaya rollout tidak macet di meja TU. HP kedua
     * dan seterusnya menunggu approve admin -- jalur ganti HP itulah vektor
     * titip absen, jadi hanya itu yang dijaga.
     *
     * @throws ValidationException
     */
    public function __invoke(User $guru, string $uuid, ?string $userAgent): Perangkat
    {
        $terdaftarUntukOrangLain = Perangkat::query()
            ->where('uuid', $uuid)
            ->where('user_id', '!=', $guru->id)
            ->exists();

        if ($terdaftarUntukOrangLain) {
            throw ValidationException::withMessages([
                'device_uuid' => 'HP ini sudah terdaftar untuk guru lain. Hubungi TU.',
            ]);
        }

        $milikSendiri = Perangkat::query()
            ->where('uuid', $uuid)
            ->where('user_id', $guru->id)
            ->first();

        if ($milikSendiri !== null) {
            return $milikSendiri;
        }

        $sudahAdaYangAktif = Perangkat::query()
            ->where('user_id', $guru->id)
            ->where('status', StatusPerangkat::Active)
            ->exists();

        $perangkat = Perangkat::create([
            'user_id' => $guru->id,
            'uuid' => $uuid,
            'label' => self::label($userAgent),
            'user_agent' => $userAgent,
            'status' => $sudahAdaYangAktif ? StatusPerangkat::Pending : StatusPerangkat::Active,
        ]);

        if ($perangkat->status === StatusPerangkat::Active) {
            $perangkat->forceFill(['approved_at' => now()])->save();
        }

        return $perangkat;
    }

    /**
     * Nama perangkat yang cukup untuk dikenali admin di daftar.
     */
    public static function label(?string $userAgent): string
    {
        $userAgent ??= '';

        return match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            default => 'Perangkat lain',
        };
    }
}
```

- [ ] **Step 4: Write the form request**

Run: `php artisan make:request DaftarkanPerangkatRequest --no-interaction`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DaftarkanPerangkatRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'uuid'],
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

Run: `php artisan make:controller PerangkatController --no-interaction`

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\DaftarkanPerangkat;
use App\Enums\StatusPerangkat;
use App\Http\Requests\DaftarkanPerangkatRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PerangkatController extends Controller
{
    /**
     * Ikat HP yang dipakai guru saat ini ke akunnya.
     */
    public function store(DaftarkanPerangkatRequest $request, DaftarkanPerangkat $daftarkan): RedirectResponse
    {
        $perangkat = $daftarkan(
            $request->user(),
            $request->string('device_uuid')->toString(),
            $request->userAgent(),
        );

        $aktif = $perangkat->status === StatusPerangkat::Active;

        Inertia::flash('toast', [
            'type' => $aktif ? 'success' : 'info',
            'message' => $aktif
                ? 'HP ini berhasil diikat ke akunmu.'
                : 'Permintaan ganti HP dikirim. Tunggu persetujuan TU.',
        ]);

        // Cookie ini cadangan localStorage, bukan lapisan keamanan: nilainya sama
        // persis. Gunanya supaya guru tidak terlihat sebagai perangkat baru ketika
        // penyimpanan browser dibersihkan atau dievakuasi iOS.
        return to_route('dashboard')->withCookie(
            cookie()->forever('perangkat_uuid', $perangkat->uuid, null, null, null, true, false, 'lax')
        );
    }
}
```

- [ ] **Step 6: Register the route**

Di `routes/web.php`, dalam group `['auth', 'verified']`:

```php
    Route::post('perangkat', [PerangkatController::class, 'store'])->name('perangkat.store');
```

Import `use App\Http\Controllers\PerangkatController;`.

- [ ] **Step 7: Verify the cookie is encrypted**

Cookie Laravel dienkripsi oleh middleware `EncryptCookies` di group `web`. Periksa `bootstrap/app.php`: pastikan `perangkat_uuid` **tidak** ada di daftar pengecualian enkripsi. Kalau ada `->encryptCookies(except: [...])`, jangan tambahkan nama ini ke situ.

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/PerangkatPendaftaranTest.php`
Expected: PASS, 7 tests.

- [ ] **Step 9: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
git add -A
git commit -m "feat: bind a teacher's phone, first device active, rest pending"
```

---

## Task 8: Pipeline tap — semua gerbang penolak, jejak audit, dan passkey step-up

Task terbesar dan paling penting. Semua keputusan dibuat di server; klien hanya mengirim koordinat mentah.

**Passkey memakai pola step-up berbasis session**, bukan assertion yang ikut di payload tap. Alasannya konkret: `@laravel/passkeys` mengekspos `Passkeys.verify({ routes: { options, submit } })` yang menjalankan seluruh upacara WebAuthn dan bisa diarahkan ke endpoint kita. Memakai itu berarti tidak ada satu baris pun konversi base64url ditulis tangan di frontend, dan lapisan biometriknya bisa diuji otomatis. Pola ini sama dengan konfirmasi password Laravel: verifikasi menandai session, tap berikutnya membacanya.

Konsekuensi keamanan yang disengaja: **guru yang punya passkey WAJIB verifikasi**. Kalau tidak, lapisan biometrik jadi opsional bagi orang yang memilikinya dan jaminan anti-titip bubar. Guru yang memang tidak punya passkey tetap boleh absen, ditandai `terverifikasi = false` — itulah kelonggaran yang disetujui di spec, dan hanya itu.

**Files:**
- Create: `app/Actions/Absensi/CatatAbsensi.php`
- Create: `app/Http/Requests/CatatAbsensiRequest.php`
- Create: `app/Http/Controllers/AbsensiController.php`
- Create: `app/Http/Controllers/AbsensiPasskeyController.php`
- Modify: `routes/web.php`
- Modify: `tests/Pest.php` (helper `guruSiapAbsen`)
- Test: `tests/Feature/AbsensiTapTest.php`

**Interfaces:**
- Consumes: `Jarak`, `Perangkat`, `Lokasi`, `JadwalKerja`, `Absensi`, `AbsensiAttempt`, semua enum dari Task 1/5/6, `Laravel\Passkeys\Actions\GenerateVerificationOptions`, `Laravel\Passkeys\Actions\VerifyPasskey`, `Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest`, `Laravel\Passkeys\Support\WebAuthn`.
- Produces:
  - `CatatAbsensi::AKURASI_MAKSIMAL_METER` = `75`
  - `CatatAbsensi::UMUR_VERIFIKASI_DETIK` = `120`
  - `CatatAbsensi::KEY_VERIFIKASI` = `'absensi.passkey_verified_at'` — session key penanda verifikasi biometrik
  - `CatatAbsensi::__invoke(User $guru, TipeTap $tipe, float $latitude, float $longitude, int $accuracy, string $perangkatUuid, bool $passkeyTerverifikasi): Absensi` — melempar `ValidationException` pada key `tap` di setiap penolakan, setelah menulis baris `absensi_attempts`
  - Route `dashboard` (GET → `AbsensiController@index`), `absensi.store` (POST, `throttle:10,1`), `absensi.passkey-options` (GET, JSON), `absensi.passkey-verify` (POST, JSON)
  - Helper test global `guruSiapAbsen(): array{0: User, 1: Perangkat, 2: Lokasi}`

- [ ] **Step 1: Add the test helper**

Di `tests/Pest.php`, ganti fungsi contoh `something()` dengan:

```php
/**
 * Seorang guru dengan HP terikat, satu lokasi absen aktif, dan jadwal kerja terisi.
 *
 * @return array{0: App\Models\User, 1: App\Models\Perangkat, 2: App\Models\Lokasi}
 */
function guruSiapAbsen(): array
{
    app(Database\Seeders\JadwalKerjaSeeder::class)->run();

    $guru = App\Models\User::factory()->create();

    return [
        $guru,
        App\Models\Perangkat::factory()->for($guru)->create(),
        App\Models\Lokasi::factory()->create(),
    ];
}

/**
 * Daftarkan satu passkey palsu supaya hasPasskeysEnabled() bernilai true.
 *
 * Kredensialnya tidak sah untuk upacara WebAuthn -- itu memang tidak diperlukan:
 * yang diuji di sini adalah gerbang "punya passkey wajib verifikasi", bukan
 * validasi tanda tangan.
 */
function pasangPasskeyPalsu(App\Models\User $guru): void
{
    Laravel\Passkeys\Passkey::forceCreate([
        'user_id' => $guru->id,
        'name' => 'HP Guru',
        'credential_id' => 'kredensial-uji-'.$guru->id,
        'credential' => [],
    ]);
}
```

- [ ] **Step 2: Write the failing test**

Semua test membekukan waktu ke **Senin 7 September 2026** supaya hari kerja dan status terlambat deterministik. Jadwal seeder: masuk 07:00, toleransi 10 menit, pulang 14:00.

```php
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

    expect(Absensi::where('user_id', $guru->id)->value('status'))->toBe(StatusAbsensi::Terlambat->value);
});

test('tap tepat di batas toleransi masih hadir', function () {
    Carbon::setTestNow('2026-09-07 07:10:00');

    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), payloadTap($perangkat));

    expect(Absensi::where('user_id', $guru->id)->value('status'))->toBe(StatusAbsensi::Hadir->value);
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
```

Jalur "assertion WebAuthn yang benar-benar sah" **tidak** diuji otomatis. Menandatanganinya butuh virtual authenticator (Chrome DevTools Protocol), di luar cakupan suite ini. Yang diuji: options terbentuk, penanda session dihormati, kedaluwarsa ditolak, sekali pakai, dan guru berpasskey tidak bisa melewatinya. Upacara sidik jari di HP nyata tetap **wajib** dicoba manual sebelum rilis — catat di checklist rilis, jangan diklaim lolos oleh test.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/AbsensiTapTest.php`
Expected: FAIL — route `absensi.store` tidak terdefinisi.

- [ ] **Step 4: Write the form request**

```php
<?php

namespace App\Http\Requests;

use App\Enums\TipeTap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatatAbsensiRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipe' => ['required', Rule::enum(TipeTap::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'device_uuid' => ['required', 'uuid'],
        ];
    }
}
```

- [ ] **Step 5: Write the CatatAbsensi action**

```php
<?php

namespace App\Actions\Absensi;

use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\StatusPerangkat;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\Perangkat;
use App\Models\User;
use App\Support\Jarak;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatatAbsensi
{
    /**
     * Batas ketidakpastian GPS yang masih diterima, dalam meter.
     *
     * Di atas ini koordinat terlalu kabur untuk membuktikan guru ada di dalam
     * radius sekolah. iOS yang hanya diberi izin lokasi kasar akan jatuh di sini,
     * jadi pesan errornya harus menyebut "Lokasi Tepat" secara eksplisit.
     */
    public const AKURASI_MAKSIMAL_METER = 75;

    /**
     * Umur maksimal penanda verifikasi biometrik, dalam detik.
     */
    public const UMUR_VERIFIKASI_DETIK = 120;

    /**
     * Session key penanda verifikasi biometrik yang baru saja lolos.
     */
    public const KEY_VERIFIKASI = 'absensi.passkey_verified_at';

    /**
     * Catat satu tap. Setiap penolakan tetap menulis jejak di absensi_attempts.
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $guru,
        TipeTap $tipe,
        float $latitude,
        float $longitude,
        int $accuracy,
        string $perangkatUuid,
        bool $passkeyTerverifikasi,
    ): Absensi {
        $dasar = [
            'user_id' => $guru->id,
            'tipe' => $tipe,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meter' => $accuracy,
            'perangkat_uuid' => $perangkatUuid,
            'terverifikasi' => false,
        ];

        $perangkat = Perangkat::query()
            ->where('user_id', $guru->id)
            ->where('uuid', $perangkatUuid)
            ->where('status', StatusPerangkat::Active)
            ->first();

        if ($perangkat === null) {
            $this->tolak($dasar, HasilTap::PerangkatAsing, 'HP ini belum terdaftar untuk akunmu. Hubungi TU.');
        }

        if ($accuracy > self::AKURASI_MAKSIMAL_METER) {
            $this->tolak(
                $dasar,
                HasilTap::AkurasiBuruk,
                "Sinyal GPS lemah (±{$accuracy} m). Coba di luar ruangan dan aktifkan Lokasi Tepat.",
            );
        }

        $lokasi = $this->lokasiTerdekat($latitude, $longitude);

        if ($lokasi === null) {
            $this->tolak($dasar, HasilTap::LuarRadius, 'Belum ada lokasi absen aktif. Hubungi TU.');
        }

        $jarak = Jarak::meter($latitude, $longitude, $lokasi->latitude, $lokasi->longitude);

        $dasar = [...$dasar, 'jarak_meter' => $jarak, 'lokasi_id' => $lokasi->id];

        if ($jarak > $lokasi->radius_meter) {
            $this->tolak(
                $dasar,
                HasilTap::LuarRadius,
                "Kamu {$jarak} m dari sekolah. Absen hanya bisa dalam {$lokasi->radius_meter} m.",
            );
        }

        // Guru yang punya passkey tidak boleh melewati biometrik. Kalau boleh,
        // lapisan "membuktikan orang" jadi opsional dan anti-titip bubar.
        if ($guru->hasPasskeysEnabled() && ! $passkeyTerverifikasi) {
            $this->tolak($dasar, HasilTap::PasskeyInvalid, 'Verifikasi sidik jari dulu, lalu tap lagi.');
        }

        $absensi = Absensi::query()->firstOrNew([
            'user_id' => $guru->id,
            'tanggal' => today()->toDateString(),
        ]);

        $kolom = $tipe === TipeTap::Masuk ? 'masuk_attempt_id' : 'pulang_attempt_id';

        if ($absensi->{$kolom} !== null) {
            $this->tolak($dasar, HasilTap::Duplikat, "Sudah absen {$tipe->value} hari ini.");
        }

        if ($tipe === TipeTap::Pulang && $absensi->masuk_attempt_id === null) {
            $this->tolak($dasar, HasilTap::BelumMasuk, 'Belum ada absen masuk hari ini.');
        }

        return DB::transaction(function () use ($guru, $tipe, $dasar, $passkeyTerverifikasi, $absensi, $kolom): Absensi {
            $attempt = AbsensiAttempt::create([
                ...$dasar,
                'terverifikasi' => $passkeyTerverifikasi,
                'hasil' => HasilTap::Diterima,
            ]);

            $jadwal = JadwalKerja::query()->where('day_of_week', now()->dayOfWeek)->first();

            $absensi->user_id = $guru->id;
            $absensi->tanggal = today();
            $absensi->{$kolom} = $attempt->id;

            if ($tipe === TipeTap::Masuk) {
                $absensi->status = $this->statusMasuk($jadwal);
            } else {
                $absensi->pulang_cepat = $this->pulangCepat($jadwal);
            }

            $absensi->save();

            return $absensi;
        });
    }

    /**
     * Tulis jejak percobaan yang gagal, lalu batalkan permintaan.
     *
     * @param  array<string, mixed>  $atribut
     *
     * @throws ValidationException
     */
    private function tolak(array $atribut, HasilTap $hasil, string $pesan): never
    {
        AbsensiAttempt::create([...$atribut, 'hasil' => $hasil]);

        throw ValidationException::withMessages(['tap' => $pesan]);
    }

    /**
     * Lokasi aktif terdekat dari koordinat guru.
     *
     * ponytail: seluruh lokasi aktif dimuat ke memori lalu diurut di PHP. Satu
     * sekolah punya segelintir lokasi, jadi ini gratis. Kalau jumlahnya pernah
     * mencapai ratusan, pindahkan ke query dengan bounding box lintang/bujur.
     */
    private function lokasiTerdekat(float $latitude, float $longitude): ?Lokasi
    {
        return Lokasi::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Lokasi $lokasi): int => Jarak::meter(
                $latitude,
                $longitude,
                $lokasi->latitude,
                $lokasi->longitude,
            ))
            ->first();
    }

    private function statusMasuk(?JadwalKerja $jadwal): StatusAbsensi
    {
        if ($jadwal === null) {
            return StatusAbsensi::Hadir;
        }

        $batas = today()
            ->setTimeFromTimeString($jadwal->jam_masuk)
            ->addMinutes($jadwal->toleransi_menit);

        return now()->greaterThan($batas) ? StatusAbsensi::Terlambat : StatusAbsensi::Hadir;
    }

    private function pulangCepat(?JadwalKerja $jadwal): bool
    {
        if ($jadwal === null) {
            return false;
        }

        return now()->lessThan(today()->setTimeFromTimeString($jadwal->jam_pulang));
    }
}
```

- [ ] **Step 6: Write the AbsensiController**

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatAbsensi;
use App\Enums\TipeTap;
use App\Http\Requests\CatatAbsensiRequest;
use App\Models\Absensi;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AbsensiController extends Controller
{
    /**
     * Halaman tap milik guru: jadwal hari ini, status hari ini, riwayat 30 hari.
     */
    public function index(Request $request): Response
    {
        $guru = $request->user();

        $jadwal = JadwalKerja::query()->where('day_of_week', now()->dayOfWeek)->first();

        $hariIni = Absensi::query()
            ->with(['masukAttempt', 'pulangAttempt'])
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->first();

        return Inertia::render('Dashboard', [
            'jadwal' => $jadwal === null ? null : [
                'jam_masuk' => substr($jadwal->jam_masuk, 0, 5),
                'jam_pulang' => substr($jadwal->jam_pulang, 0, 5),
                'toleransi_menit' => $jadwal->toleransi_menit,
                'is_hari_kerja' => $jadwal->is_hari_kerja,
            ],
            'hariIni' => $hariIni === null ? null : [
                'status' => $hariIni->status?->value,
                'jam_masuk' => $hariIni->masukAttempt?->created_at?->format('H:i'),
                'jam_pulang' => $hariIni->pulangAttempt?->created_at?->format('H:i'),
                'pulang_cepat' => $hariIni->pulang_cepat,
                'terverifikasi' => $hariIni->masukAttempt?->terverifikasi ?? false,
            ],
            'namaLokasi' => Lokasi::query()->where('is_active', true)->value('nama'),
            'punyaPasskey' => $guru->hasPasskeysEnabled(),
            'perangkatUuidTersimpan' => $request->cookie('perangkat_uuid'),
            'riwayat' => Absensi::query()
                ->with(['masukAttempt', 'pulangAttempt'])
                ->where('user_id', $guru->id)
                ->whereDate('tanggal', '>=', today()->subDays(29))
                ->orderByDesc('tanggal')
                ->get()
                ->map(fn (Absensi $absensi): array => [
                    'tanggal' => $absensi->tanggal->toDateString(),
                    'status' => $absensi->status?->value,
                    'jam_masuk' => $absensi->masukAttempt?->created_at?->format('H:i'),
                    'jam_pulang' => $absensi->pulangAttempt?->created_at?->format('H:i'),
                    'pulang_cepat' => $absensi->pulang_cepat,
                ])
                ->all(),
        ]);
    }

    /**
     * Terima satu tap. Semua gerbang penolak ada di CatatAbsensi.
     */
    public function store(CatatAbsensiRequest $request, CatatAbsensi $catat): RedirectResponse
    {
        $catat(
            $request->user(),
            TipeTap::from($request->string('tipe')->toString()),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (int) round((float) $request->input('accuracy')),
            $request->string('device_uuid')->toString(),
            $this->passkeyTerverifikasi($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Absen tercatat.']);

        return to_route('dashboard');
    }

    /**
     * Apakah ada verifikasi biometrik yang masih segar? Penanda sekali pakai:
     * di-pull, bukan di-get, supaya satu verifikasi tidak bisa dipakai untuk
     * dua tap.
     */
    private function passkeyTerverifikasi(Request $request): bool
    {
        $ditandai = $request->session()->pull(CatatAbsensi::KEY_VERIFIKASI);

        if (! is_string($ditandai)) {
            return false;
        }

        return Carbon::parse($ditandai)->diffInSeconds(now()) <= CatatAbsensi::UMUR_VERIFIKASI_DETIK;
    }
}
```

- [ ] **Step 7: Write the passkey step-up controller**

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;

class AbsensiPasskeyController extends Controller
{
    /**
     * Options WebAuthn untuk verifikasi ulang tepat sebelum tap.
     *
     * Session key 'passkey.verification_options' dipakai karena
     * Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest meng-hardcode
     * nama itu. Memakai key lain akan membuat verifikasi paket selalu gagal.
     */
    public function index(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $guru = $request->user();

        abort_unless(
            $guru instanceof PasskeyUser && $guru->hasPasskeysEnabled(),
            409,
            'Belum ada passkey terdaftar.',
        );

        $options = $generate($guru);

        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    /**
     * Terima assertion dari browser dan tandai session sebagai terverifikasi.
     *
     * Penanda ini berumur pendek dan sekali pakai; AbsensiController yang
     * mengonsumsinya.
     */
    public function store(PasskeyVerificationRequest $request, VerifyPasskey $verify): JsonResponse
    {
        $guru = $request->user();

        abort_unless($guru instanceof PasskeyUser, 409, 'Belum ada passkey terdaftar.');

        $verify($request->credential(), $request->verificationOptions(), $guru);

        $request->session()->put(CatatAbsensi::KEY_VERIFIKASI, now()->toIso8601String());

        return response()->json(['verified' => true]);
    }
}
```

- [ ] **Step 8: Register the routes**

Di `routes/web.php`, hapus `Route::inertia('dashboard', 'Dashboard')->name('dashboard');` dan tulis:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AbsensiController::class, 'index'])->name('dashboard');

    Route::post('absensi', [AbsensiController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('absensi.store');

    Route::get('absensi/passkey-options', [AbsensiPasskeyController::class, 'index'])
        ->name('absensi.passkey-options');

    Route::post('absensi/passkey-verify', [AbsensiPasskeyController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('absensi.passkey-verify');

    Route::post('perangkat', [PerangkatController::class, 'store'])->name('perangkat.store');
});
```

Import ketiga controller di bagian atas file.

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/AbsensiTapTest.php`
Expected: PASS, 21 tests.

Kalau test "luar radius" gagal karena jaraknya terlalu kecil, hitung ulang: 0,003° lintang ≈ 333 m. Jangan mengecilkan radius lokasi supaya test lolos — perbaiki koordinatnya.

- [ ] **Step 10: Run the whole suite, then commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
git add -A
git commit -m "feat: attendance tap pipeline with geofence, device, and biometric gates"
```

---
## Task 9: Halaman tap guru

`Dashboard.svelte` sekarang berisi demo keuangan (Fino, dompet, transaksi) dengan komentar `ponytail:` yang menandainya sebagai placeholder. Ganti isinya; **pertahankan** bahasa visualnya: `.g-tile`, `.g-tone-*`, `.g-display`, token `--g-*`, radius 28px, dan gaya kartu yang sudah ada.

Verifikasi passkey memakai `usePasskeyVerify` dari `@laravel/passkeys/svelte` — pola yang sudah dipakai `resources/js/components/PasskeyVerify.svelte`, lengkap dengan override `routes`. Jangan menulis konversi base64url sendiri.

**Files:**
- Create: `resources/js/lib/perangkat.ts`
- Create: `resources/js/components/TapButton.svelte`
- Modify: `resources/js/pages/Dashboard.svelte` (ganti seluruh isi)
- Test: `tests/Feature/DashboardTest.php` (perluas yang sudah ada)

**Interfaces:**
- Consumes: props dari `AbsensiController@index` (`jadwal`, `hariIni`, `namaLokasi`, `punyaPasskey`, `perangkatUuidTersimpan`, `riwayat`); route Wayfinder `@/actions/App/Http/Controllers/AbsensiController` (`store`), `@/actions/App/Http/Controllers/AbsensiPasskeyController` (`index`, `store`), `@/actions/App/Http/Controllers/PerangkatController` (`store`).
- Produces:
  - `resources/js/lib/perangkat.ts` — `bacaDeviceUuid(cadangan: string | null): string | null`, `simpanDeviceUuid(uuid: string): void`, `buatDeviceUuid(): string`.
  - `TapButton.svelte` — props `{ tipe: 'masuk' | 'pulang', punyaPasskey: boolean, deviceUuid: string | null, disabled?: boolean, label: string }`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/DashboardTest.php — ganti seluruh isi

use App\Models\Absensi;
use App\Models\User;
use Illuminate\Support\Carbon;

test('tamu diarahkan ke halaman login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('guru melihat halaman tap dengan jadwal hari ini', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');

    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('jadwal.jam_masuk', '07:00')
            ->where('jadwal.jam_pulang', '14:00')
            ->where('jadwal.is_hari_kerja', true)
            ->where('hariIni', null)
            ->where('namaLokasi', 'Gerbang Utama')
            ->where('punyaPasskey', false)
        );
});

test('status hari ini muncul setelah tap masuk', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');

    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)->post(route('absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ]);

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('hariIni.status', 'hadir')
            ->where('hariIni.jam_masuk', '07:00')
            ->where('hariIni.jam_pulang', null)
        );
});

test('riwayat hanya memuat 30 hari terakhir milik guru sendiri', function () {
    Carbon::setTestNow('2026-09-07 07:00:00');

    [$guru] = guruSiapAbsen();

    Absensi::factory()->for($guru)->create(['tanggal' => today()->subDays(5)]);
    Absensi::factory()->for($guru)->create(['tanggal' => today()->subDays(40)]);
    Absensi::factory()->for(User::factory())->create(['tanggal' => today()]);

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('riwayat', 1));
});

test('uuid perangkat dari cookie diteruskan sebagai prop', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->withCookie('perangkat_uuid', $perangkat->uuid)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('perangkatUuidTersimpan', $perangkat->uuid));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/DashboardTest.php`
Expected: FAIL pada assertion prop — controller sudah ada dari Task 8, tapi test lama masih memakai `Route::inertia`. Kalau semua sudah lolos di sini, lanjut: Task 8 memang sudah menyediakan controllernya, dan task ini menambah sisi frontend.

Catatan: `withCookie` pada test mengirim cookie **tanpa** enkripsi, sedangkan middleware `EncryptCookies` mengharapkan nilai terenkripsi. Kalau test terakhir gagal karena itu, ganti menjadi `$this->withUnencryptedCookie('perangkat_uuid', $perangkat->uuid)`.

- [ ] **Step 3: Write the device uuid helper**

```ts
// resources/js/lib/perangkat.ts

const KUNCI = 'absensi.device_uuid';

/**
 * Baca uuid perangkat. localStorage lebih dulu; kalau kosong, pakai nilai
 * cadangan dari cookie yang dikirim server dan tulis ulang ke localStorage.
 *
 * Dua penyimpanan harus hilang bersamaan sebelum guru terlihat sebagai
 * perangkat baru. Cookie bukan lapisan keamanan -- nilainya sama persis.
 */
export function bacaDeviceUuid(cadangan: string | null): string | null {
    try {
        const tersimpan = localStorage.getItem(KUNCI);

        if (tersimpan) {
            return tersimpan;
        }
    } catch {
        // Safari private mode melempar begitu localStorage disentuh.
    }

    if (cadangan) {
        simpanDeviceUuid(cadangan);

        return cadangan;
    }

    return null;
}

export function simpanDeviceUuid(uuid: string): void {
    try {
        localStorage.setItem(KUNCI, uuid);
    } catch {
        // Tidak fatal: cookie dari server tetap jadi cadangan.
    }
}

/**
 * crypto.randomUUID hanya ada di secure context. Aplikasi ini memang wajib
 * HTTPS karena geolocation, jadi tidak ada fallback yang perlu ditulis.
 */
export function buatDeviceUuid(): string {
    return crypto.randomUUID();
}
```

- [ ] **Step 4: Write TapButton.svelte**

```svelte
<!-- resources/js/components/TapButton.svelte -->
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { usePasskeyVerify } from '@laravel/passkeys/svelte';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import { store as tapAbsensi } from '@/actions/App/Http/Controllers/AbsensiController';
    import {
        index as passkeyOptions,
        store as passkeyVerifyRoute,
    } from '@/actions/App/Http/Controllers/AbsensiPasskeyController';
    import InputError from '@/components/InputError.svelte';
    import { Spinner } from '@/components/ui/spinner';

    type Props = {
        tipe: 'masuk' | 'pulang';
        label: string;
        punyaPasskey: boolean;
        deviceUuid: string | null;
        disabled?: boolean;
    };

    let {
        tipe,
        label,
        punyaPasskey,
        deviceUuid,
        disabled = false,
    }: Props = $props();

    let sedangProses = $state(false);
    let pesanGalat = $state('');
    let posisi: GeolocationPosition | null = null;

    const passkeyVerify = usePasskeyVerify({
        routes: {
            options: passkeyOptions.url(),
            submit: passkeyVerifyRoute.url(),
        },
        onSuccess: () => kirim(),
        onError: (pesan: string) => {
            pesanGalat = pesan || 'Verifikasi sidik jari gagal.';
            sedangProses = false;
        },
    });

    function ambilPosisi(): Promise<GeolocationPosition> {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10_000,
                maximumAge: 0,
            });
        });
    }

    function kirim(): void {
        if (!posisi || !deviceUuid) {
            sedangProses = false;

            return;
        }

        router.post(
            tapAbsensi.url(),
            {
                tipe,
                latitude: posisi.coords.latitude,
                longitude: posisi.coords.longitude,
                accuracy: Math.round(posisi.coords.accuracy),
                device_uuid: deviceUuid,
            },
            {
                preserveScroll: true,
                onError: (errors: Record<string, string>) => {
                    pesanGalat = errors.tap ?? 'Absen gagal. Coba lagi.';
                },
                onFinish: () => {
                    sedangProses = false;
                },
            },
        );
    }

    async function tap(): Promise<void> {
        pesanGalat = '';

        if (!navigator.onLine) {
            pesanGalat = 'Butuh koneksi internet untuk absen.';

            return;
        }

        if (!deviceUuid) {
            pesanGalat = 'HP ini belum terdaftar. Muat ulang halaman.';

            return;
        }

        sedangProses = true;

        try {
            posisi = await ambilPosisi();
        } catch (galat) {
            sedangProses = false;
            pesanGalat = pesanLokasi(galat);

            return;
        }

        if (punyaPasskey && passkeyVerify.isSupported) {
            // onSuccess akan memanggil kirim().
            passkeyVerify.verify();

            return;
        }

        kirim();
    }

    function pesanLokasi(galat: unknown): string {
        const kode = (galat as GeolocationPositionError | undefined)?.code;

        if (kode === 1) {
            return 'Izin lokasi ditolak. Buka Pengaturan aplikasi, aktifkan Lokasi dan Lokasi Tepat.';
        }

        if (kode === 3) {
            return 'GPS terlalu lama merespons. Coba di luar ruangan.';
        }

        return 'Lokasi tidak terbaca. Aktifkan GPS lalu coba lagi.';
    }
</script>

<div class="grid gap-3">
    <button
        type="button"
        class="tap"
        onclick={tap}
        disabled={disabled || sedangProses}
    >
        {#if sedangProses}
            <Spinner />
            Membaca lokasi...
        {:else}
            {#if punyaPasskey}
                <Fingerprint class="size-6" aria-hidden="true" />
            {:else}
                <MapPin class="size-6" aria-hidden="true" />
            {/if}
            {label}
        {/if}
    </button>

    {#if pesanGalat}
        <InputError message={pesanGalat} />
    {/if}
</div>

<style>
    .tap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        width: 100%;
        min-height: 5.5rem;
        border-radius: 28px;
        background: linear-gradient(
            115deg,
            var(--g-lime) 0%,
            var(--g-lime-2) 100%
        );
        color: var(--g-lime-ink);
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        /* Cegah double-tap zoom, seleksi teks, dan kilatan tap di mobile. */
        touch-action: manipulation;
        user-select: none;
        transition:
            border-radius 0.5s var(--g-emphasized),
            transform 0.3s var(--g-emphasized);
    }

    .tap:active {
        transform: scale(0.98);
    }

    .tap:hover:not(:disabled) {
        border-radius: 56px 28px 56px 28px;
    }

    .tap:disabled {
        opacity: 0.55;
    }
</style>
```

- [ ] **Step 5: Rewrite Dashboard.svelte**

```svelte
<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Absensi', href: dashboard() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import { store as daftarkanPerangkat } from '@/actions/App/Http/Controllers/PerangkatController';
    import AppHead from '@/components/AppHead.svelte';
    import InstallPrompt from '@/components/InstallPrompt.svelte';
    import TapButton from '@/components/TapButton.svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        bacaDeviceUuid,
        buatDeviceUuid,
        simpanDeviceUuid,
    } from '@/lib/perangkat';

    type Jadwal = {
        jam_masuk: string;
        jam_pulang: string;
        toleransi_menit: number;
        is_hari_kerja: boolean;
    };

    type Hari = {
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
        terverifikasi: boolean;
    };

    type Riwayat = {
        tanggal: string;
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
    };

    let {
        jadwal,
        hariIni,
        namaLokasi,
        punyaPasskey,
        perangkatUuidTersimpan,
        riwayat,
    }: {
        jadwal: Jadwal | null;
        hariIni: Hari | null;
        namaLokasi: string | null;
        punyaPasskey: boolean;
        perangkatUuidTersimpan: string | null;
        riwayat: Riwayat[];
    } = $props();

    let deviceUuid = $state<string | null>(null);
    let jam = $state(waktuSekarang());

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const tanggalPendek = new Intl.DateTimeFormat('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

    function waktuSekarang(): string {
        return new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(new Date());
    }

    $effect(() => {
        const jeda = setInterval(() => {
            jam = waktuSekarang();
        }, 1000);

        return () => clearInterval(jeda);
    });

    $effect(() => {
        const tersimpan = bacaDeviceUuid(perangkatUuidTersimpan);

        if (tersimpan) {
            deviceUuid = tersimpan;

            return;
        }

        const baru = buatDeviceUuid();
        simpanDeviceUuid(baru);
        deviceUuid = baru;

        router.post(
            daftarkanPerangkat.url(),
            { device_uuid: baru },
            { preserveScroll: true },
        );
    });

    const sudahMasuk = $derived(hariIni?.jam_masuk != null);
    const sudahPulang = $derived(hariIni?.jam_pulang != null);
    const selesai = $derived(sudahMasuk && sudahPulang);

    const labelStatus: Record<string, string> = {
        hadir: 'Hadir',
        terlambat: 'Terlambat',
    };

    function labelRiwayat(baris: Riwayat): string {
        const dasar = baris.status ? (labelStatus[baris.status] ?? baris.status) : '-';

        return baris.pulang_cepat ? `${dasar} · pulang cepat` : dasar;
    }
</script>

<AppHead title="Absensi" />

<div class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 py-5 safe-bottom sm:px-6">
    <InstallPrompt />

    <section class="g-tile g-tone-plain">
        <p class="text-xs font-semibold tracking-[0.14em] uppercase text-muted-foreground">
            {tanggalPanjang.format(new Date())}
        </p>
        <p class="g-display text-[clamp(2.5rem,10vw,3.5rem)] font-mono tabular-nums">
            {jam}
        </p>

        {#if jadwal && jadwal.is_hari_kerja}
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <CalendarClock class="size-4" aria-hidden="true" />
                Masuk {jadwal.jam_masuk} · Pulang {jadwal.jam_pulang} · Toleransi {jadwal.toleransi_menit} menit
            </p>
        {:else}
            <p class="text-sm text-muted-foreground">
                Hari ini bukan hari kerja.
            </p>
        {/if}

        {#if namaLokasi}
            <p class="flex items-center gap-2 text-sm text-muted-foreground">
                <MapPin class="size-4" aria-hidden="true" />
                Absen hanya di sekitar {namaLokasi}
            </p>
        {/if}
    </section>

    <section class="g-tile {sudahMasuk ? 'g-tone-green' : 'g-tone-yellow'}">
        <h3>Status hari ini</h3>

        {#if hariIni}
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">
                    {hariIni.status ? (labelStatus[hariIni.status] ?? hariIni.status) : 'Tercatat'}
                </Badge>
                {#if hariIni.pulang_cepat}
                    <Badge variant="outline">Pulang cepat</Badge>
                {/if}
                {#if !hariIni.terverifikasi}
                    <Badge variant="outline" class="gap-1">
                        <ShieldAlert class="size-3" aria-hidden="true" />
                        Tanpa biometrik
                    </Badge>
                {/if}
            </div>
            <p>
                Masuk {hariIni.jam_masuk ?? '-'} · Pulang {hariIni.jam_pulang ?? '-'}
            </p>
        {:else}
            <p>Belum ada absen hari ini.</p>
        {/if}
    </section>

    {#if selesai}
        <p class="flex items-center justify-center gap-2 py-2 text-sm text-muted-foreground">
            <CircleCheck class="size-4" aria-hidden="true" />
            Absen hari ini sudah lengkap.
        </p>
    {:else}
        <TapButton
            tipe={sudahMasuk ? 'pulang' : 'masuk'}
            label={sudahMasuk ? 'TAP PULANG' : 'TAP MASUK'}
            {punyaPasskey}
            {deviceUuid}
            disabled={deviceUuid === null}
        />
    {/if}

    <section class="g-tile g-tone-plain">
        <h3>Riwayat 30 hari</h3>

        {#if riwayat.length === 0}
            <p>Belum ada riwayat absen.</p>
        {:else}
            <ul class="divide-y divide-border">
                {#each riwayat as baris (baris.tanggal)}
                    <li class="flex items-center justify-between gap-4 py-2.5 text-sm">
                        <span class="font-medium">
                            {tanggalPendek.format(new Date(baris.tanggal))}
                        </span>
                        <span class="text-muted-foreground">
                            {baris.jam_masuk ?? '-'} – {baris.jam_pulang ?? '-'}
                        </span>
                        <span class="font-medium">{labelRiwayat(baris)}</span>
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
```

`InstallPrompt` dibuat di Task 15; sampai task itu selesai, komentari dua baris yang memakainya (import dan tag) supaya build tidak pecah.

- [ ] **Step 6: Generate routes and typecheck**

```bash
php artisan wayfinder:generate
npm run build
```

Periksa nama export hasil Wayfinder di `resources/js/actions/App/Http/Controllers/`. Kalau `AbsensiPasskeyController` mengekspor nama selain `index`/`store`, sesuaikan import — jangan mengarang nama.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/DashboardTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 8: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
npm run build
git add -A
git commit -m "feat: teacher tap page with geolocation and biometric step-up"
```

---

## Task 10: Izin milik guru — pengajuan, tumpang tindih, lampiran privat

**Files:**
- Create: `app/Policies/IzinPolicy.php`
- Create: `app/Http/Requests/AjukanIzinRequest.php`
- Create: `app/Http/Controllers/IzinController.php`
- Create: `resources/js/pages/izin/Index.svelte`
- Modify: `routes/web.php`
- Test: `tests/Feature/IzinTest.php`

**Interfaces:**
- Consumes: `Izin`, `TipeIzin`, `StatusIzin`, `User`.
- Produces: route `izin.index` (GET), `izin.store` (POST), `izin.lampiran` (GET, `can:view,izin`); `IzinPolicy::view(User, Izin): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/IzinTest.php

use App\Enums\StatusIzin;
use App\Models\Izin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guru bisa mengajukan izin', function () {
    $guru = User::factory()->create();

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-11',
        'alasan' => 'Demam tinggi.',
    ])->assertRedirect(route('izin.index'));

    $izin = Izin::where('user_id', $guru->id)->firstOrFail();

    expect($izin->status)->toBe(StatusIzin::Pending)
        ->and($izin->lampiran_path)->toBeNull();
});

test('lampiran disimpan di disk privat', function () {
    Storage::fake('local');

    $guru = User::factory()->create();

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-10',
        'alasan' => 'Surat dokter terlampir.',
        'lampiran' => UploadedFile::fake()->create('surat.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $path = Izin::where('user_id', $guru->id)->value('lampiran_path');

    expect($path)->toStartWith('izin/');
    Storage::disk('local')->assertExists($path);
});

test('lampiran bukan pdf atau gambar ditolak', function () {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())->post(route('izin.store'), [
        'tipe' => 'izin',
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-10',
        'alasan' => 'Ada acara keluarga.',
        'lampiran' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
    ])->assertSessionHasErrors('lampiran');
});

test('tanggal selesai tidak boleh sebelum tanggal mulai', function () {
    $this->actingAs(User::factory()->create())->post(route('izin.store'), [
        'tipe' => 'izin',
        'tanggal_mulai' => '2026-09-11',
        'tanggal_selesai' => '2026-09-10',
        'alasan' => 'Salah isi.',
    ])->assertSessionHasErrors('tanggal_selesai');
});

test('izin yang tumpang tindih dengan pengajuan sendiri ditolak', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->create([
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-12',
    ]);

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-12',
        'tanggal_selesai' => '2026-09-13',
        'alasan' => 'Masih sakit.',
    ])->assertSessionHasErrors('tanggal_mulai');

    expect(Izin::where('user_id', $guru->id)->count())->toBe(1);
});

test('izin yang ditolak tidak menghalangi pengajuan baru', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->ditolak()->create([
        'tanggal_mulai' => '2026-09-10',
        'tanggal_selesai' => '2026-09-12',
    ]);

    $this->actingAs($guru)->post(route('izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-11',
        'tanggal_selesai' => '2026-09-11',
        'alasan' => 'Pengajuan ulang.',
    ])->assertSessionHasNoErrors();
});

test('guru hanya melihat izin miliknya sendiri', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->count(2)->create();
    Izin::factory()->for(User::factory())->count(3)->create();

    $this->actingAs($guru)
        ->get(route('izin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('izin/Index')->has('izins', 2));
});

test('guru tidak bisa mengunduh lampiran izin guru lain', function () {
    Storage::fake('local');

    $milikOrangLain = Izin::factory()->for(User::factory())->create([
        'lampiran_path' => 'izin/rahasia.pdf',
    ]);

    Storage::disk('local')->put('izin/rahasia.pdf', 'isi');

    $this->actingAs(User::factory()->create())
        ->get(route('izin.lampiran', $milikOrangLain))
        ->assertForbidden();
});

test('guru bisa mengunduh lampirannya sendiri', function () {
    Storage::fake('local');

    $guru = User::factory()->create();
    $izin = Izin::factory()->for($guru)->create(['lampiran_path' => 'izin/surat.pdf']);

    Storage::disk('local')->put('izin/surat.pdf', 'isi');

    $this->actingAs($guru)->get(route('izin.lampiran', $izin))->assertOk();
});

test('izin tanpa lampiran mengembalikan 404 saat diunduh', function () {
    $guru = User::factory()->create();
    $izin = Izin::factory()->for($guru)->create(['lampiran_path' => null]);

    $this->actingAs($guru)->get(route('izin.lampiran', $izin))->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/IzinTest.php`
Expected: FAIL — route `izin.store` tidak terdefinisi.

- [ ] **Step 3: Write the policy**

Run: `php artisan make:policy IzinPolicy --model=Izin --no-interaction`

Ganti isinya dengan hanya apa yang dipakai:

```php
<?php

namespace App\Policies;

use App\Models\Izin;
use App\Models\User;

class IzinPolicy
{
    /**
     * Guru hanya boleh melihat izinnya sendiri; admin boleh semua.
     */
    public function view(User $user, Izin $izin): bool
    {
        return $izin->user_id === $user->id || $user->can('admin');
    }
}
```

Laravel menemukan policy ini otomatis lewat konvensi nama (`App\Policies\IzinPolicy` untuk `App\Models\Izin`); tidak perlu registrasi manual.

- [ ] **Step 4: Write the form request**

```php
<?php

namespace App\Http\Requests;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\Izin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjukanIzinRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipe' => ['required', Rule::enum(TipeIzin::class)],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * Tolak rentang yang bertabrakan dengan izin pending atau disetujui milik
     * guru yang sama. Izin yang sudah ditolak tidak menghalangi apa pun.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $mulai = $this->date('tanggal_mulai');
            $selesai = $this->date('tanggal_selesai');

            if ($mulai === null || $selesai === null) {
                return;
            }

            $bertabrakan = Izin::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('status', [StatusIzin::Pending, StatusIzin::Disetujui])
                ->where('tanggal_mulai', '<=', $selesai)
                ->where('tanggal_selesai', '>=', $mulai)
                ->exists();

            if ($bertabrakan) {
                $validator->errors()->add(
                    'tanggal_mulai',
                    'Sudah ada pengajuan izin pada rentang tanggal itu.',
                );
            }
        });
    }
}
```

Aturan `after_or_equal:today` sengaja dipasang: izin diajukan untuk ke depan. Kalau TU perlu memasukkan izin surut, itu lewat halaman admin, bukan lewat form guru.

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjukanIzinRequest;
use App\Models\Izin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IzinController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('izin/Index', [
            'izins' => Izin::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (Izin $izin): array => [
                    'id' => $izin->id,
                    'tipe' => $izin->tipe->value,
                    'tanggal_mulai' => $izin->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $izin->tanggal_selesai->toDateString(),
                    'alasan' => $izin->alasan,
                    'status' => $izin->status->value,
                    'catatan_review' => $izin->catatan_review,
                    'ada_lampiran' => $izin->lampiran_path !== null,
                ])
                ->all(),
        ]);
    }

    public function store(AjukanIzinRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);

        // Disk 'local' bukan 'public': surat dokter tidak boleh bisa dibuka
        // dengan menebak URL.
        $data['lampiran_path'] = $request->file('lampiran')?->store('izin', 'local');
        $data['user_id'] = $request->user()->id;

        Izin::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan izin terkirim.']);

        return to_route('izin.index');
    }

    /**
     * Unduh lampiran. Otorisasi lewat IzinPolicy::view, bukan lewat URL rahasia.
     */
    public function lampiran(Izin $izin): StreamedResponse
    {
        abort_if($izin->lampiran_path === null, 404);

        return Storage::disk('local')->download($izin->lampiran_path);
    }
}
```

- [ ] **Step 6: Register the routes**

Dalam group `['auth', 'verified']` di `routes/web.php`:

```php
    Route::get('izin', [IzinController::class, 'index'])->name('izin.index');
    Route::post('izin', [IzinController::class, 'store'])->name('izin.store');
    Route::get('izin/{izin}/lampiran', [IzinController::class, 'lampiran'])
        ->middleware('can:view,izin')
        ->name('izin.lampiran');
```

- [ ] **Step 7: Write the Svelte page**

```svelte
<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Izin', href: '/izin' }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { store as ajukanIzin } from '@/actions/App/Http/Controllers/IzinController';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type IzinBaris = {
        id: number;
        tipe: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        status: string;
        catatan_review: string | null;
        ada_lampiran: boolean;
    };

    let { izins }: { izins: IzinBaris[] } = $props();

    const form = useForm({
        tipe: 'izin',
        tanggal_mulai: '',
        tanggal_selesai: '',
        alasan: '',
        lampiran: null as File | null,
    });

    const warnaStatus: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
        pending: 'secondary',
        disetujui: 'default',
        ditolak: 'destructive',
    };

    function kirim(event: SubmitEvent): void {
        event.preventDefault();

        $form.post(ajukanIzin.url(), {
            forceFormData: true,
            onSuccess: () => $form.reset(),
        });
    }
</script>

<AppHead title="Izin" />

<div class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 py-5 safe-bottom sm:px-6">
    <section class="g-tile g-tone-plain">
        <h3>Ajukan izin</h3>

        <form class="grid gap-4" onsubmit={kirim}>
            <div class="grid gap-2">
                <Label for="tipe">Jenis</Label>
                <select
                    id="tipe"
                    bind:value={$form.tipe}
                    class="h-10 rounded-md border border-input bg-background px-3 text-base"
                >
                    <option value="izin">Izin</option>
                    <option value="sakit">Sakit</option>
                    <option value="cuti">Cuti</option>
                </select>
                <InputError message={$form.errors.tipe} />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="mulai">Tanggal mulai</Label>
                    <Input id="mulai" type="date" bind:value={$form.tanggal_mulai} />
                    <InputError message={$form.errors.tanggal_mulai} />
                </div>
                <div class="grid gap-2">
                    <Label for="selesai">Tanggal selesai</Label>
                    <Input id="selesai" type="date" bind:value={$form.tanggal_selesai} />
                    <InputError message={$form.errors.tanggal_selesai} />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="alasan">Alasan</Label>
                <textarea
                    id="alasan"
                    rows="3"
                    bind:value={$form.alasan}
                    class="rounded-md border border-input bg-background p-3 text-base"
                ></textarea>
                <InputError message={$form.errors.alasan} />
            </div>

            <div class="grid gap-2">
                <Label for="lampiran">Lampiran (opsional, PDF/JPG/PNG, maks 2 MB)</Label>
                <input
                    id="lampiran"
                    type="file"
                    accept="application/pdf,image/jpeg,image/png"
                    onchange={(event) => {
                        $form.lampiran = event.currentTarget.files?.[0] ?? null;
                    }}
                    class="text-sm"
                />
                <InputError message={$form.errors.lampiran} />
            </div>

            <Button type="submit" disabled={$form.processing}>Kirim pengajuan</Button>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Pengajuan saya</h3>

        {#if izins.length === 0}
            <p>Belum ada pengajuan izin.</p>
        {:else}
            <ul class="divide-y divide-border">
                {#each izins as izin (izin.id)}
                    <li class="grid gap-1 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium capitalize">{izin.tipe}</span>
                            <Badge variant={warnaStatus[izin.status] ?? 'secondary'}>
                                {izin.status}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {izin.tanggal_mulai} – {izin.tanggal_selesai}
                        </p>
                        <p class="text-sm">{izin.alasan}</p>
                        {#if izin.catatan_review}
                            <p class="text-sm text-muted-foreground">
                                Catatan TU: {izin.catatan_review}
                            </p>
                        {/if}
                        {#if izin.ada_lampiran}
                            <a
                                href={`/izin/${izin.id}/lampiran`}
                                class="text-sm font-medium text-primary"
                            >
                                Unduh lampiran
                            </a>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/IzinTest.php`
Expected: PASS, 10 tests.

- [ ] **Step 9: Quality gates and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
npm run build
git add -A
git commit -m "feat: teacher leave requests with private attachments"
```

---
## Task 11: Admin — approve dan reject izin

**Files:**
- Create: `app/Http/Requests/Admin/ReviewIzinRequest.php`
- Create: `app/Http/Controllers/Admin/IzinController.php`
- Create: `resources/js/pages/admin/Izin.svelte`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/IzinReviewTest.php`

**Interfaces:**
- Consumes: `Izin`, `StatusIzin`, gate `admin`.
- Produces: route `admin.izin.index` (GET), `admin.izin.update` (PATCH). Kedua route di group `can:admin`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Admin/IzinReviewTest.php

use App\Enums\StatusIzin;
use App\Models\Izin;
use App\Models\User;

test('guru tidak boleh membuka daftar izin admin', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.izin.index'))
        ->assertForbidden();
});

test('admin melihat semua izin', function () {
    Izin::factory()->for(User::factory())->count(3)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.izin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/Izin')->has('izins', 3));
});

test('admin bisa menyetujui izin', function () {
    $admin = User::factory()->admin()->create();
    $izin = Izin::factory()->for(User::factory())->create();

    $this->actingAs($admin)
        ->patch(route('admin.izin.update', $izin), ['status' => 'disetujui'])
        ->assertRedirect(route('admin.izin.index'));

    $izin->refresh();

    expect($izin->status)->toBe(StatusIzin::Disetujui)
        ->and($izin->reviewed_by)->toBe($admin->id)
        ->and($izin->reviewed_at)->not->toBeNull();
});

test('admin bisa menolak izin dengan catatan', function () {
    $admin = User::factory()->admin()->create();
    $izin = Izin::factory()->for(User::factory())->create();

    $this->actingAs($admin)->patch(route('admin.izin.update', $izin), [
        'status' => 'ditolak',
        'catatan_review' => 'Surat dokter tidak terlampir.',
    ]);

    $izin->refresh();

    expect($izin->status)->toBe(StatusIzin::Ditolak)
        ->and($izin->catatan_review)->toBe('Surat dokter tidak terlampir.');
});

test('status pending tidak bisa dikirim sebagai hasil review', function () {
    $izin = Izin::factory()->for(User::factory())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.izin.update', $izin), ['status' => 'pending'])
        ->assertSessionHasErrors('status');
});

test('guru tidak bisa menyetujui izinnya sendiri', function () {
    $guru = User::factory()->create();
    $izin = Izin::factory()->for($guru)->create();

    $this->actingAs($guru)
        ->patch(route('admin.izin.update', $izin), ['status' => 'disetujui'])
        ->assertForbidden();

    expect($izin->refresh()->status)->toBe(StatusIzin::Pending);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/IzinReviewTest.php`
Expected: FAIL — route `admin.izin.index` tidak terdefinisi.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatusIzin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIzinRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Pending bukan hasil review, jadi tidak diterima di sini.
            'status' => ['required', Rule::enum(StatusIzin::class)->only([
                StatusIzin::Disetujui,
                StatusIzin::Ditolak,
            ])],
            'catatan_review' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusIzin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewIzinRequest;
use App\Models\Izin;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IzinController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Izin', [
            'izins' => Izin::query()
                ->with('user:id,name,nip')
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (Izin $izin): array => [
                    'id' => $izin->id,
                    'guru' => $izin->user->name,
                    'nip' => $izin->user->nip,
                    'tipe' => $izin->tipe->value,
                    'tanggal_mulai' => $izin->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $izin->tanggal_selesai->toDateString(),
                    'alasan' => $izin->alasan,
                    'status' => $izin->status->value,
                    'catatan_review' => $izin->catatan_review,
                    'ada_lampiran' => $izin->lampiran_path !== null,
                ])
                ->all(),
        ]);
    }

    public function update(ReviewIzinRequest $request, Izin $izin): RedirectResponse
    {
        $izin->forceFill([
            'status' => StatusIzin::from($request->string('status')->toString()),
            'catatan_review' => $request->input('catatan_review'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Izin diperbarui.']);

        return to_route('admin.izin.index');
    }
}
```

- [ ] **Step 5: Register the admin route group**

Di `routes/web.php`, tambah setelah group guru:

```php
Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('izin', [Admin\IzinController::class, 'index'])->name('izin.index');
        Route::patch('izin/{izin}', [Admin\IzinController::class, 'update'])->name('izin.update');
    });
```

Import `use App\Http\Controllers\Admin;` supaya `Admin\IzinController` terbaca, atau import tiap controller penuh — ikuti gaya yang sudah dipakai di file itu.

- [ ] **Step 6: Write the Svelte page**

```svelte
<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Izin masuk', href: '/admin/izin' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';

    type IzinBaris = {
        id: number;
        guru: string;
        nip: string | null;
        tipe: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        status: string;
        catatan_review: string | null;
        ada_lampiran: boolean;
    };

    let { izins }: { izins: IzinBaris[] } = $props();

    let catatan = $state<Record<number, string>>({});

    function review(id: number, status: 'disetujui' | 'ditolak'): void {
        router.patch(
            `/admin/izin/${id}`,
            { status, catatan_review: catatan[id] ?? null },
            { preserveScroll: true },
        );
    }
</script>

<AppHead title="Izin masuk" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <h3>Pengajuan izin</h3>

        {#if izins.length === 0}
            <p>Belum ada pengajuan.</p>
        {:else}
            <ul class="divide-y divide-border">
                {#each izins as izin (izin.id)}
                    <li class="grid gap-2 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium">
                                {izin.guru}{izin.nip ? ` · ${izin.nip}` : ''}
                            </span>
                            <Badge variant={izin.status === 'pending' ? 'secondary' : 'outline'}>
                                {izin.status}
                            </Badge>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {izin.tipe} · {izin.tanggal_mulai} – {izin.tanggal_selesai}
                        </p>
                        <p class="text-sm">{izin.alasan}</p>

                        {#if izin.ada_lampiran}
                            <a href={`/izin/${izin.id}/lampiran`} class="text-sm font-medium text-primary">
                                Unduh lampiran
                            </a>
                        {/if}

                        {#if izin.status === 'pending'}
                            <input
                                type="text"
                                placeholder="Catatan (opsional)"
                                bind:value={catatan[izin.id]}
                                class="h-10 rounded-md border border-input bg-background px-3 text-base"
                            />
                            <div class="flex gap-2">
                                <Button size="sm" onclick={() => review(izin.id, 'disetujui')}>
                                    Setujui
                                </Button>
                                <Button size="sm" variant="outline" onclick={() => review(izin.id, 'ditolak')}>
                                    Tolak
                                </Button>
                            </div>
                        {:else if izin.catatan_review}
                            <p class="text-sm text-muted-foreground">Catatan: {izin.catatan_review}</p>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
```

- [ ] **Step 7: Run tests, quality gates, commit**

```bash
php artisan test --compact tests/Feature/Admin/IzinReviewTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
git add -A
git commit -m "feat: admin leave approval"
```

Expected: PASS, 6 tests.

---

## Task 12: Admin — halaman pengaturan (lokasi, jadwal, hari libur)

Satu halaman, tiga kartu, satu controller. Tidak dipecah jadi tiga route — tidak ada yang bertambah jelas dengan itu.

**Files:**
- Create: `app/Http/Controllers/Admin/PengaturanController.php`
- Create: `app/Http/Requests/Admin/SimpanLokasiRequest.php`, `SimpanJadwalRequest.php`, `SimpanHariLiburRequest.php`
- Create: `resources/js/pages/admin/Pengaturan.svelte`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/PengaturanTest.php`

**Interfaces:**
- Consumes: `Lokasi`, `JadwalKerja`, `HariLibur`, gate `admin`.
- Produces: route `admin.pengaturan.edit` (GET), `admin.lokasi.store` (POST), `admin.jadwal.update` (PUT), `admin.hari-libur.store` (POST), `admin.hari-libur.destroy` (DELETE).

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Admin/PengaturanTest.php

use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;

test('guru tidak boleh membuka pengaturan', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.pengaturan.edit'))
        ->assertForbidden();
});

test('admin melihat lokasi, jadwal, dan hari libur', function () {
    $this->seed(JadwalKerjaSeeder::class);
    Lokasi::factory()->create();
    HariLibur::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pengaturan.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Pengaturan')
            ->has('lokasis', 1)
            ->has('jadwals', 7)
            ->has('hariLiburs', 1)
        );
});

test('admin bisa menambah lokasi', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.lokasi.store'), [
        'nama' => 'Gerbang Belakang',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius_meter' => 80,
        'is_active' => true,
    ])->assertRedirect(route('admin.pengaturan.edit'));

    expect(Lokasi::where('nama', 'Gerbang Belakang')->value('radius_meter'))->toBe(80);
});

test('radius lokasi dibatasi masuk akal', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.lokasi.store'), [
            'nama' => 'Terlalu Luas',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius_meter' => 50_000,
        ])
        ->assertSessionHasErrors('radius_meter');
});

test('koordinat di luar rentang bumi ditolak', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.lokasi.store'), [
            'nama' => 'Antah Berantah',
            'latitude' => 200,
            'longitude' => 106.8,
        ])
        ->assertSessionHasErrors('latitude');
});

test('admin bisa mengubah jadwal kerja', function () {
    $this->seed(JadwalKerjaSeeder::class);

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.jadwal.update'), [
        'jadwals' => [
            ['day_of_week' => 1, 'jam_masuk' => '06:30', 'jam_pulang' => '13:30', 'toleransi_menit' => 5, 'is_hari_kerja' => true],
        ],
    ])->assertRedirect(route('admin.pengaturan.edit'));

    $senin = JadwalKerja::where('day_of_week', 1)->firstOrFail();

    expect($senin->jam_masuk)->toStartWith('06:30')
        ->and($senin->toleransi_menit)->toBe(5);
});

test('admin bisa menambah dan menghapus hari libur', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.hari-libur.store'), [
        'tanggal' => '2026-08-17',
        'nama' => 'HUT RI',
    ]);

    $libur = HariLibur::where('nama', 'HUT RI')->firstOrFail();

    $this->actingAs($admin)->delete(route('admin.hari-libur.destroy', $libur));

    expect(HariLibur::count())->toBe(0);
});

test('tanggal libur ganda ditolak validasi', function () {
    HariLibur::factory()->create(['tanggal' => '2026-08-17']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.hari-libur.store'), ['tanggal' => '2026-08-17', 'nama' => 'Duplikat'])
        ->assertSessionHasErrors('tanggal');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/PengaturanTest.php`
Expected: FAIL — route `admin.pengaturan.edit` tidak terdefinisi.

- [ ] **Step 3: Write the form requests**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanLokasiRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            // Batas atas menjaga radius tidak dilebarkan sampai kehilangan makna.
            'radius_meter' => ['required', 'integer', 'min:20', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanJadwalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jadwals' => ['required', 'array', 'min:1', 'max:7'],
            'jadwals.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'jadwals.*.jam_masuk' => ['required', 'date_format:H:i'],
            'jadwals.*.jam_pulang' => ['required', 'date_format:H:i', 'after:jadwals.*.jam_masuk'],
            'jadwals.*.toleransi_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'jadwals.*.is_hari_kerja' => ['required', 'boolean'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SimpanHariLiburRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'unique:hari_liburs,tanggal'],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanHariLiburRequest;
use App\Http\Requests\Admin\SimpanJadwalRequest;
use App\Http\Requests\Admin\SimpanLokasiRequest;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Pengaturan', [
            'lokasis' => Lokasi::query()->orderBy('nama')->get([
                'id', 'nama', 'latitude', 'longitude', 'radius_meter', 'is_active',
            ]),
            'jadwals' => JadwalKerja::query()->orderBy('day_of_week')->get([
                'day_of_week', 'jam_masuk', 'jam_pulang', 'toleransi_menit', 'is_hari_kerja',
            ]),
            'hariLiburs' => HariLibur::query()->orderBy('tanggal')->get(['id', 'tanggal', 'nama']),
        ]);
    }

    public function simpanLokasi(SimpanLokasiRequest $request): RedirectResponse
    {
        Lokasi::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function simpanJadwal(SimpanJadwalRequest $request): RedirectResponse
    {
        /** @var array<int, array<string, mixed>> $jadwals */
        $jadwals = $request->validated()['jadwals'];

        foreach ($jadwals as $jadwal) {
            JadwalKerja::updateOrCreate(
                ['day_of_week' => $jadwal['day_of_week']],
                [
                    'jam_masuk' => $jadwal['jam_masuk'].':00',
                    'jam_pulang' => $jadwal['jam_pulang'].':00',
                    'toleransi_menit' => $jadwal['toleransi_menit'],
                    'is_hari_kerja' => $jadwal['is_hari_kerja'],
                ],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal kerja disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function simpanHariLibur(SimpanHariLiburRequest $request): RedirectResponse
    {
        HariLibur::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hari libur ditambahkan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function hapusHariLibur(HariLibur $hariLibur): RedirectResponse
    {
        $hariLibur->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hari libur dihapus.']);

        return to_route('admin.pengaturan.edit');
    }
}
```

- [ ] **Step 5: Register the routes**

Dalam group admin:

```php
        Route::get('pengaturan', [Admin\PengaturanController::class, 'edit'])->name('pengaturan.edit');
        Route::post('lokasi', [Admin\PengaturanController::class, 'simpanLokasi'])->name('lokasi.store');
        Route::put('jadwal', [Admin\PengaturanController::class, 'simpanJadwal'])->name('jadwal.update');
        Route::post('hari-libur', [Admin\PengaturanController::class, 'simpanHariLibur'])->name('hari-libur.store');
        Route::delete('hari-libur/{hariLibur}', [Admin\PengaturanController::class, 'hapusHariLibur'])->name('hari-libur.destroy');
```

- [ ] **Step 6: Write the Svelte page**

```svelte
<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Pengaturan', href: '/admin/pengaturan' }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type LokasiBaris = {
        id: number;
        nama: string;
        latitude: number;
        longitude: number;
        radius_meter: number;
        is_active: boolean;
    };

    type JadwalBaris = {
        day_of_week: number;
        jam_masuk: string;
        jam_pulang: string;
        toleransi_menit: number;
        is_hari_kerja: boolean;
    };

    type LiburBaris = { id: number; tanggal: string; nama: string };

    let {
        lokasis,
        jadwals,
        hariLiburs,
    }: {
        lokasis: LokasiBaris[];
        jadwals: JadwalBaris[];
        hariLiburs: LiburBaris[];
    } = $props();

    const namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    const formLokasi = useForm({
        nama: '',
        latitude: '',
        longitude: '',
        radius_meter: 100,
        is_active: true,
    });

    const formJadwal = useForm({
        jadwals: jadwals.map((jadwal) => ({
            ...jadwal,
            jam_masuk: jadwal.jam_masuk.slice(0, 5),
            jam_pulang: jadwal.jam_pulang.slice(0, 5),
        })),
    });

    const formLibur = useForm({ tanggal: '', nama: '' });

    /** Isi koordinat dari GPS perangkat admin -- dipakai saat berdiri di gerbang. */
    function pakaiLokasiSaya(): void {
        navigator.geolocation.getCurrentPosition((posisi) => {
            $formLokasi.latitude = posisi.coords.latitude.toFixed(7);
            $formLokasi.longitude = posisi.coords.longitude.toFixed(7);
        });
    }
</script>

<AppHead title="Pengaturan" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <h3>Lokasi absen</h3>

        <ul class="divide-y divide-border">
            {#each lokasis as lokasi (lokasi.id)}
                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                    <span class="font-medium">{lokasi.nama}</span>
                    <span class="text-muted-foreground">
                        {lokasi.latitude}, {lokasi.longitude} · {lokasi.radius_meter} m
                    </span>
                </li>
            {/each}
        </ul>

        <form
            class="grid gap-3 sm:grid-cols-2"
            onsubmit={(event) => {
                event.preventDefault();
                $formLokasi.post('/admin/lokasi', { onSuccess: () => $formLokasi.reset() });
            }}
        >
            <div class="grid gap-2 sm:col-span-2">
                <Label for="nama">Nama lokasi</Label>
                <Input id="nama" bind:value={$formLokasi.nama} />
                <InputError message={$formLokasi.errors.nama} />
            </div>
            <div class="grid gap-2">
                <Label for="lat">Latitude</Label>
                <Input id="lat" bind:value={$formLokasi.latitude} />
                <InputError message={$formLokasi.errors.latitude} />
            </div>
            <div class="grid gap-2">
                <Label for="lng">Longitude</Label>
                <Input id="lng" bind:value={$formLokasi.longitude} />
                <InputError message={$formLokasi.errors.longitude} />
            </div>
            <div class="grid gap-2">
                <Label for="radius">Radius (meter)</Label>
                <Input id="radius" type="number" bind:value={$formLokasi.radius_meter} />
                <InputError message={$formLokasi.errors.radius_meter} />
            </div>
            <div class="flex items-end gap-2">
                <Button type="button" variant="outline" onclick={pakaiLokasiSaya}>
                    Pakai lokasi saya
                </Button>
                <Button type="submit" disabled={$formLokasi.processing}>Tambah</Button>
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Jadwal kerja</h3>

        <form
            class="grid gap-3"
            onsubmit={(event) => {
                event.preventDefault();
                $formJadwal.put('/admin/jadwal');
            }}
        >
            {#each $formJadwal.jadwals as jadwal, index (jadwal.day_of_week)}
                <div class="grid grid-cols-2 items-end gap-2 sm:grid-cols-5">
                    <span class="text-sm font-medium">{namaHari[jadwal.day_of_week]}</span>
                    <Input type="time" bind:value={$formJadwal.jadwals[index].jam_masuk} />
                    <Input type="time" bind:value={$formJadwal.jadwals[index].jam_pulang} />
                    <Input type="number" bind:value={$formJadwal.jadwals[index].toleransi_menit} />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" bind:checked={$formJadwal.jadwals[index].is_hari_kerja} />
                        Hari kerja
                    </label>
                </div>
            {/each}

            <Button type="submit" disabled={$formJadwal.processing}>Simpan jadwal</Button>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Hari libur</h3>

        <ul class="divide-y divide-border">
            {#each hariLiburs as libur (libur.id)}
                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                    <span>{libur.tanggal} · {libur.nama}</span>
                    <Button
                        size="sm"
                        variant="ghost"
                        onclick={() => router.delete(`/admin/hari-libur/${libur.id}`, { preserveScroll: true })}
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </Button>
                </li>
            {/each}
        </ul>

        <form
            class="grid gap-3 sm:grid-cols-3"
            onsubmit={(event) => {
                event.preventDefault();
                $formLibur.post('/admin/hari-libur', { onSuccess: () => $formLibur.reset() });
            }}
        >
            <div class="grid gap-2">
                <Label for="tanggal">Tanggal</Label>
                <Input id="tanggal" type="date" bind:value={$formLibur.tanggal} />
                <InputError message={$formLibur.errors.tanggal} />
            </div>
            <div class="grid gap-2">
                <Label for="namaLibur">Keterangan</Label>
                <Input id="namaLibur" bind:value={$formLibur.nama} />
                <InputError message={$formLibur.errors.nama} />
            </div>
            <div class="flex items-end">
                <Button type="submit" disabled={$formLibur.processing}>Tambah</Button>
            </div>
        </form>
    </section>
</div>
```

- [ ] **Step 7: Run tests, quality gates, commit**

```bash
php artisan test --compact tests/Feature/Admin/PengaturanTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
git add -A
git commit -m "feat: admin settings for locations, schedule, and holidays"
```

Expected: PASS, 8 tests.

---

## Task 13: Admin — kelola guru dan approve ganti HP

**Files:**
- Create: `app/Http/Requests/Admin/SimpanGuruRequest.php`
- Create: `app/Http/Controllers/Admin/GuruController.php`
- Create: `resources/js/pages/admin/Guru.svelte`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/GuruTest.php`

**Interfaces:**
- Consumes: `User`, `Role`, `Perangkat`, `StatusPerangkat`, gate `admin`.
- Produces: route `admin.guru.index` (GET), `admin.guru.store` (POST), `admin.guru.update` (PATCH), `admin.perangkat.update` (PATCH).

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Admin/GuruTest.php

use App\Enums\Role;
use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guru tidak boleh membuka daftar guru admin', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.guru.index'))
        ->assertForbidden();
});

test('admin melihat daftar guru beserta perangkatnya', function () {
    $guru = User::factory()->create();
    Perangkat::factory()->for($guru)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.guru.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/Guru')->has('gurus'));
});

test('admin bisa membuat akun guru', function () {
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.guru.store'), [
        'name' => 'Bu Aminah',
        'nip' => '1987654321',
        'email' => 'aminah@sekolah.test',
        'password' => 'rahasia-panjang-sekali',
    ])->assertRedirect(route('admin.guru.index'));

    $guru = User::where('email', 'aminah@sekolah.test')->firstOrFail();

    expect($guru->role)->toBe(Role::Guru)
        ->and($guru->is_active)->toBeTrue()
        ->and(Hash::check('rahasia-panjang-sekali', $guru->password))->toBeTrue()
        ->and($guru->email_verified_at)->not->toBeNull();
});

test('email guru harus unik', function () {
    $ada = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.store'), [
            'name' => 'Kembar',
            'email' => $ada->email,
            'password' => 'rahasia-panjang-sekali',
        ])
        ->assertSessionHasErrors('email');
});

test('admin bisa menonaktifkan guru', function () {
    $guru = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.guru.update', $guru), ['is_active' => false]);

    expect($guru->refresh()->is_active)->toBeFalse();
});

test('admin bisa menyetujui perangkat pending dan mencabut yang lama', function () {
    $admin = User::factory()->admin()->create();
    $guru = User::factory()->create();

    $lama = Perangkat::factory()->for($guru)->create();
    $baru = Perangkat::factory()->for($guru)->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.perangkat.update', $baru), ['status' => 'active'])
        ->assertRedirect(route('admin.guru.index'));

    expect($baru->refresh()->status)->toBe(StatusPerangkat::Active)
        ->and($baru->approved_by)->toBe($admin->id)
        ->and($lama->refresh()->status)->toBe(StatusPerangkat::Revoked);
});

test('admin bisa mencabut perangkat', function () {
    $perangkat = Perangkat::factory()->for(User::factory())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.perangkat.update', $perangkat), ['status' => 'revoked']);

    expect($perangkat->refresh()->status)->toBe(StatusPerangkat::Revoked);
});

test('guru tidak bisa menyetujui perangkatnya sendiri', function () {
    $guru = User::factory()->create();
    $pending = Perangkat::factory()->for($guru)->pending()->create();

    $this->actingAs($guru)
        ->patch(route('admin.perangkat.update', $pending), ['status' => 'active'])
        ->assertForbidden();

    expect($pending->refresh()->status)->toBe(StatusPerangkat::Pending);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/GuruTest.php`
Expected: FAIL — route `admin.guru.index` tidak terdefinisi.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SimpanGuruRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default()],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatusPerangkat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanGuruRequest;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GuruController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Guru', [
            'gurus' => User::query()
                ->with(['perangkats' => fn ($query) => $query->orderByDesc('created_at')])
                ->orderBy('name')
                ->get()
                ->map(fn (User $guru): array => [
                    'id' => $guru->id,
                    'name' => $guru->name,
                    'nip' => $guru->nip,
                    'email' => $guru->email,
                    'role' => $guru->role->value,
                    'is_active' => $guru->is_active,
                    'perangkats' => $guru->perangkats->map(fn (Perangkat $perangkat): array => [
                        'id' => $perangkat->id,
                        'label' => $perangkat->label,
                        'status' => $perangkat->status->value,
                        'terdaftar' => $perangkat->created_at?->format('d M Y'),
                    ])->all(),
                ])
                ->all(),
        ]);
    }

    /**
     * Akun guru dibuat manual oleh admin. Tidak ada registrasi mandiri.
     */
    public function store(SimpanGuruRequest $request): RedirectResponse
    {
        $guru = User::create($request->safe()->only(['name', 'nip', 'email', 'password']));

        // Akun internal: tidak perlu siklus verifikasi email.
        $guru->forceFill([
            'role' => Role::Guru,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun guru dibuat.']);

        return to_route('admin.guru.index');
    }

    public function update(Request $request, User $guru): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'role' => ['sometimes', Rule::enum(Role::class)],
        ]);

        $guru->forceFill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data guru diperbarui.']);

        return to_route('admin.guru.index');
    }

    /**
     * Setujui atau cabut satu perangkat.
     *
     * Menyetujui perangkat baru otomatis mencabut perangkat aktif lain milik guru
     * yang sama: satu guru, satu HP aktif. Tanpa ini, guru bisa menumpuk HP aktif
     * dan titip absen kembali terbuka.
     */
    public function updatePerangkat(Request $request, Perangkat $perangkat): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(StatusPerangkat::class)->only([
                StatusPerangkat::Active,
                StatusPerangkat::Revoked,
            ])],
        ]);

        $status = StatusPerangkat::from($data['status']);

        DB::transaction(function () use ($request, $perangkat, $status): void {
            if ($status === StatusPerangkat::Active) {
                Perangkat::query()
                    ->where('user_id', $perangkat->user_id)
                    ->where('id', '!=', $perangkat->id)
                    ->where('status', StatusPerangkat::Active)
                    ->update(['status' => StatusPerangkat::Revoked->value]);
            }

            $perangkat->forceFill([
                'status' => $status,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perangkat diperbarui.']);

        return to_route('admin.guru.index');
    }
}
```

Tambahkan relasi ke `app/Models/User.php`:

```php
    /**
     * @return HasMany<Perangkat, $this>
     */
    public function perangkats(): HasMany
    {
        return $this->hasMany(Perangkat::class);
    }
```

Import `use Illuminate\Database\Eloquent\Relations\HasMany;` dan tambahkan `@property-read \Illuminate\Database\Eloquent\Collection<int, Perangkat> $perangkats` ke blok PHPDoc.

- [ ] **Step 5: Register the routes**

Dalam group admin:

```php
        Route::get('guru', [Admin\GuruController::class, 'index'])->name('guru.index');
        Route::post('guru', [Admin\GuruController::class, 'store'])->name('guru.store');
        Route::patch('guru/{guru}', [Admin\GuruController::class, 'update'])->name('guru.update');
        Route::patch('perangkat/{perangkat}', [Admin\GuruController::class, 'updatePerangkat'])->name('perangkat.update');
```

- [ ] **Step 6: Write the Svelte page**

```svelte
<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Guru', href: '/admin/guru' }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type PerangkatBaris = {
        id: number;
        label: string;
        status: string;
        terdaftar: string | null;
    };

    type GuruBaris = {
        id: number;
        name: string;
        nip: string | null;
        email: string;
        role: string;
        is_active: boolean;
        perangkats: PerangkatBaris[];
    };

    let { gurus }: { gurus: GuruBaris[] } = $props();

    const form = useForm({ name: '', nip: '', email: '', password: '' });

    function ubahAktif(guru: GuruBaris): void {
        router.patch(
            `/admin/guru/${guru.id}`,
            { is_active: !guru.is_active },
            { preserveScroll: true },
        );
    }

    function ubahPerangkat(id: number, status: 'active' | 'revoked'): void {
        router.patch(`/admin/perangkat/${id}`, { status }, { preserveScroll: true });
    }
</script>

<AppHead title="Guru" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <h3>Buat akun guru</h3>

        <form
            class="grid gap-3 sm:grid-cols-2"
            onsubmit={(event) => {
                event.preventDefault();
                $form.post('/admin/guru', { onSuccess: () => $form.reset() });
            }}
        >
            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" bind:value={$form.name} />
                <InputError message={$form.errors.name} />
            </div>
            <div class="grid gap-2">
                <Label for="nip">NIP (opsional)</Label>
                <Input id="nip" bind:value={$form.nip} />
                <InputError message={$form.errors.nip} />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" type="email" bind:value={$form.email} />
                <InputError message={$form.errors.email} />
            </div>
            <div class="grid gap-2">
                <Label for="password">Password awal</Label>
                <Input id="password" type="password" bind:value={$form.password} />
                <InputError message={$form.errors.password} />
            </div>
            <div class="sm:col-span-2">
                <Button type="submit" disabled={$form.processing}>Buat akun</Button>
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Daftar guru</h3>

        <ul class="divide-y divide-border">
            {#each gurus as guru (guru.id)}
                <li class="grid gap-2 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-medium">
                                {guru.name}{guru.nip ? ` · ${guru.nip}` : ''}
                            </p>
                            <p class="text-sm text-muted-foreground">{guru.email}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant={guru.is_active ? 'default' : 'destructive'}>
                                {guru.is_active ? 'aktif' : 'nonaktif'}
                            </Badge>
                            <Badge variant="outline">{guru.role}</Badge>
                            <Button size="sm" variant="outline" onclick={() => ubahAktif(guru)}>
                                {guru.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                            </Button>
                        </div>
                    </div>

                    {#if guru.perangkats.length > 0}
                        <ul class="grid gap-1 rounded-xl bg-muted/50 p-3">
                            {#each guru.perangkats as perangkat (perangkat.id)}
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span>
                                        {perangkat.label} · {perangkat.terdaftar ?? '-'}
                                        <Badge variant="outline" class="ml-2">{perangkat.status}</Badge>
                                    </span>
                                    <span class="flex gap-2">
                                        {#if perangkat.status !== 'active'}
                                            <Button size="sm" onclick={() => ubahPerangkat(perangkat.id, 'active')}>
                                                Setujui
                                            </Button>
                                        {/if}
                                        {#if perangkat.status !== 'revoked'}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onclick={() => ubahPerangkat(perangkat.id, 'revoked')}
                                            >
                                                Cabut
                                            </Button>
                                        {/if}
                                    </span>
                                </li>
                            {/each}
                        </ul>
                    {:else}
                        <p class="text-sm text-muted-foreground">Belum ada HP terdaftar.</p>
                    {/if}
                </li>
            {/each}
        </ul>
    </section>
</div>
```

- [ ] **Step 7: Run tests, quality gates, commit**

```bash
php artisan test --compact tests/Feature/Admin/GuruTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
git add -A
git commit -m "feat: admin teacher accounts and device approval"
```

Expected: PASS, 8 tests.

---
## Task 14: Rekap bulanan, badge anomali, dan export CSV

Satu penyimpangan sadar dari spec §7 langkah 5. Spec menulis "tidak ada apa pun & tanggal ≤ hari ini → Alfa". Diterapkan apa adanya, setiap guru terlihat **Alfa** sepanjang pagi sebelum ia tap — admin akan membaca itu sebagai kesalahan sistem. Karena itu `RekapBulanan` memakai `tanggal < hari ini` untuk Alfa, dan hari ini yang belum ada jejaknya berstatus `Belum`. Fungsi murni `StatusHarian::resolve` tidak berubah; yang berubah hanya nilai yang dikirim pemanggil.

**Files:**
- Create: `app/Actions/Absensi/RekapBulanan.php`
- Create: `app/Http/Controllers/Admin/RekapController.php`
- Create: `resources/js/pages/admin/Rekap.svelte`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/RekapTest.php`

**Interfaces:**
- Consumes: `StatusHarian`, `StatusHari`, `Absensi`, `AbsensiAttempt`, `Izin`, `HariLibur`, `JadwalKerja`, `User`, `Role`, `StatusIzin`, `HasilTap`.
- Produces:
  - `RekapBulanan::__invoke(int $tahun, int $bulan, ?int $userId = null): array{tanggals: list<string>, baris: list<array{...}>}` — bentuk lengkap ada di PHPDoc kelasnya.
  - Route `admin.rekap.index` (GET), `admin.rekap.export` (GET, CSV).

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/Admin/RekapTest.php

use App\Actions\Absensi\RekapBulanan;
use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\TipeIzin;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Rabu 30 September 2026: seluruh bulan sudah lewat kecuali hari ini.
    Carbon::setTestNow('2026-09-30 10:00:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('guru tidak boleh membuka rekap', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.rekap.index'))
        ->assertForbidden();
});

test('hari kerja yang sudah lewat tanpa jejak jadi alfa', function () {
    $guru = User::factory()->create();

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    // 1 September 2026 adalah Selasa, hari kerja.
    expect($hari['2026-09-01']['status'])->toBe('alfa');
});

test('hari ini yang belum ditap belum berstatus alfa', function () {
    $guru = User::factory()->create();

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    expect($hari['2026-09-30']['status'])->toBe('belum');
});

test('minggu bukan hari kerja', function () {
    $guru = User::factory()->create();

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    // 6 September 2026 adalah Minggu.
    expect($hari['2026-09-06']['status'])->toBe('bukan_hari_kerja');
});

test('hari libur mengalahkan alfa', function () {
    $guru = User::factory()->create();
    HariLibur::factory()->create(['tanggal' => '2026-09-02', 'nama' => 'Libur Sekolah']);

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    expect($hari['2026-09-02']['status'])->toBe('libur');
});

test('izin disetujui mengisi seluruh rentangnya', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->disetujui()->create([
        'tipe' => TipeIzin::Sakit,
        'tanggal_mulai' => '2026-09-03',
        'tanggal_selesai' => '2026-09-04',
    ]);

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    expect($hari['2026-09-03']['status'])->toBe('sakit')
        ->and($hari['2026-09-04']['status'])->toBe('sakit');
});

test('izin yang masih pending tidak mengubah rekap', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->create([
        'tanggal_mulai' => '2026-09-03',
        'tanggal_selesai' => '2026-09-03',
    ]);

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    expect($hari['2026-09-03']['status'])->toBe('alfa');
});

test('absensi tanpa biometrik ditandai anomali', function () {
    $guru = User::factory()->create();

    $attempt = AbsensiAttempt::factory()->for($guru)->create([
        'tipe' => TipeTap::Masuk,
        'hasil' => HasilTap::Diterima,
        'terverifikasi' => false,
        'created_at' => '2026-09-01 07:00:00',
    ]);

    Absensi::factory()->for($guru)->create([
        'tanggal' => '2026-09-01',
        'status' => StatusAbsensi::Hadir,
        'masuk_attempt_id' => $attempt->id,
    ]);

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    expect($hari['2026-09-01']['status'])->toBe('hadir')
        ->and($hari['2026-09-01']['anomali'])->toContain('tanpa_biometrik');
});

test('koordinat identik antar guru di hari yang sama ditandai kembar', function () {
    $satu = User::factory()->create();
    $dua = User::factory()->create();

    foreach ([$satu, $dua] as $guru) {
        $attempt = AbsensiAttempt::factory()->for($guru)->create([
            'tipe' => TipeTap::Masuk,
            'hasil' => HasilTap::Diterima,
            'terverifikasi' => true,
            'latitude' => -6.1753924,
            'longitude' => 106.8271528,
            'created_at' => '2026-09-01 07:00:00',
        ]);

        Absensi::factory()->for($guru)->create([
            'tanggal' => '2026-09-01',
            'status' => StatusAbsensi::Hadir,
            'masuk_attempt_id' => $attempt->id,
        ]);
    }

    $rekap = app(RekapBulanan::class)(2026, 9, $satu->id);
    $hari = collect($rekap['baris'][0]['hari'])->keyBy('tanggal');

    expect($hari['2026-09-01']['anomali'])->toContain('koordinat_kembar');
});

test('ringkasan menghitung jumlah tiap status', function () {
    $guru = User::factory()->create();

    Izin::factory()->for($guru)->disetujui()->create([
        'tipe' => TipeIzin::Cuti,
        'tanggal_mulai' => '2026-09-03',
        'tanggal_selesai' => '2026-09-04',
    ]);

    $rekap = app(RekapBulanan::class)(2026, 9, $guru->id);

    expect($rekap['baris'][0]['ringkasan']['cuti'])->toBe(2);
});

test('admin melihat halaman rekap', function () {
    User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap.index', ['tahun' => 2026, 'bulan' => 9]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Rekap')
            ->has('rekap.baris', 1)
            ->has('rekap.tanggals', 30)
        );
});

test('export CSV berisi header dan satu baris per guru', function () {
    $guru = User::factory()->create(['name' => 'Bu Aminah', 'nip' => '123']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.rekap.export', ['tahun' => 2026, 'bulan' => 9]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $isi = $response->streamedContent();

    expect($isi)->toContain('NIP')
        ->and($isi)->toContain('Nama')
        ->and($isi)->toContain('Bu Aminah')
        ->and($isi)->toContain('2026-09-01');
});

test('guru tidak boleh mengekspor rekap', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.rekap.export', ['tahun' => 2026, 'bulan' => 9]))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/RekapTest.php`
Expected: FAIL — `App\Actions\Absensi\RekapBulanan` tidak ada.

- [ ] **Step 3: Write the RekapBulanan action**

```php
<?php

namespace App\Actions\Absensi;

use App\Enums\HasilTap;
use App\Enums\Role;
use App\Enums\StatusIzin;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\User;
use App\Support\StatusHarian;
use Illuminate\Support\Carbon;

class RekapBulanan
{
    /**
     * Susun rekap satu bulan untuk semua guru, atau satu guru saja.
     *
     * @return array{
     *     tanggals: list<string>,
     *     baris: list<array{
     *         user_id: int,
     *         nama: string,
     *         nip: string|null,
     *         hari: list<array{tanggal: string, status: string, label: string, anomali: list<string>}>,
     *         ringkasan: array<string, int>
     *     }>
     * }
     */
    public function __invoke(int $tahun, int $bulan, ?int $userId = null): array
    {
        $mulai = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $selesai = $mulai->copy()->endOfMonth();

        $tanggals = [];

        for ($hari = $mulai->copy(); $hari->lessThanOrEqualTo($selesai); $hari->addDay()) {
            $tanggals[] = $hari->toDateString();
        }

        $jadwals = JadwalKerja::query()->get()->keyBy('day_of_week');

        $liburs = HariLibur::query()
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get()
            ->keyBy(fn (HariLibur $libur): string => $libur->tanggal->toDateString());

        $gurus = User::query()
            ->where('role', Role::Guru)
            ->when($userId !== null, fn ($query) => $query->where('id', $userId))
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);

        $absensis = Absensi::query()
            ->with('masukAttempt')
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->get()
            ->keyBy(fn (Absensi $absensi): string => $absensi->user_id.'|'.$absensi->tanggal->toDateString());

        $izins = $this->petaIzin($mulai, $selesai, $userId);
        $kembar = $this->koordinatKembar($mulai, $selesai);

        $baris = [];

        foreach ($gurus as $guru) {
            $hari = [];
            $ringkasan = [];

            foreach ($tanggals as $tanggal) {
                $kunci = $guru->id.'|'.$tanggal;
                $absensi = $absensis->get($kunci);
                $jadwal = $jadwals->get(Carbon::parse($tanggal)->dayOfWeek);

                $status = StatusHarian::resolve(
                    $jadwal === null ? true : $jadwal->is_hari_kerja,
                    $liburs->has($tanggal),
                    $izins[$kunci] ?? null,
                    $absensi?->status?->value,
                    // Alfa hanya untuk tanggal yang benar-benar sudah lewat; hari
                    // ini yang belum ditap berstatus "belum", bukan alfa.
                    Carbon::parse($tanggal)->isBefore(today()),
                );

                $anomali = [];
                $attempt = $absensi?->masukAttempt;

                if ($attempt !== null && ! $attempt->terverifikasi) {
                    $anomali[] = 'tanpa_biometrik';
                }

                if ($attempt !== null && in_array($this->kunciKoordinat($attempt), $kembar, true)) {
                    $anomali[] = 'koordinat_kembar';
                }

                if ($absensi !== null && $absensi->pulang_cepat) {
                    $anomali[] = 'pulang_cepat';
                }

                if ($absensi !== null && $absensi->pulang_attempt_id === null) {
                    $anomali[] = 'belum_tap_pulang';
                }

                $hari[] = [
                    'tanggal' => $tanggal,
                    'status' => $status->value,
                    'label' => $status->label(),
                    'anomali' => $anomali,
                ];

                $ringkasan[$status->value] = ($ringkasan[$status->value] ?? 0) + 1;
            }

            $baris[] = [
                'user_id' => $guru->id,
                'nama' => $guru->name,
                'nip' => $guru->nip,
                'hari' => $hari,
                'ringkasan' => $ringkasan,
            ];
        }

        return ['tanggals' => $tanggals, 'baris' => $baris];
    }

    /**
     * Peta "userId|tanggal" => tipe izin, hanya untuk izin yang disetujui.
     *
     * @return array<string, string>
     */
    private function petaIzin(Carbon $mulai, Carbon $selesai, ?int $userId): array
    {
        $peta = [];

        $izins = Izin::query()
            ->where('status', StatusIzin::Disetujui)
            ->where('tanggal_mulai', '<=', $selesai)
            ->where('tanggal_selesai', '>=', $mulai)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->get();

        foreach ($izins as $izin) {
            $hari = $izin->tanggal_mulai->copy()->max($mulai);
            $akhir = $izin->tanggal_selesai->copy()->min($selesai);

            while ($hari->lessThanOrEqualTo($akhir)) {
                $peta[$izin->user_id.'|'.$hari->toDateString()] = $izin->tipe->value;
                $hari->addDay();
            }
        }

        return $peta;
    }

    /**
     * Koordinat yang dipakai lebih dari satu guru pada tanggal yang sama.
     *
     * Sinyal kecurangan paling murah yang tersedia: dua HP tidak pernah
     * melaporkan tujuh desimal yang identik kecuali salah satunya disuapi
     * koordinat tetap.
     *
     * @return list<string>
     */
    private function koordinatKembar(Carbon $mulai, Carbon $selesai): array
    {
        return AbsensiAttempt::query()
            ->selectRaw('date(created_at) as tanggal, latitude, longitude')
            ->where('hasil', HasilTap::Diterima)
            ->whereBetween('created_at', [$mulai->copy()->startOfDay(), $selesai->copy()->endOfDay()])
            ->groupBy('tanggal', 'latitude', 'longitude')
            ->havingRaw('count(distinct user_id) > 1')
            ->get()
            ->map(fn ($baris): string => $baris->tanggal.'|'.(float) $baris->latitude.'|'.(float) $baris->longitude)
            ->all();
    }

    private function kunciKoordinat(AbsensiAttempt $attempt): string
    {
        return $attempt->created_at?->toDateString().'|'.$attempt->latitude.'|'.$attempt->longitude;
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Absensi\RekapBulanan;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapController extends Controller
{
    public function index(Request $request, RekapBulanan $rekapBulanan): Response
    {
        [$tahun, $bulan, $userId] = $this->filter($request);

        return Inertia::render('admin/Rekap', [
            'filter' => ['tahun' => $tahun, 'bulan' => $bulan, 'user_id' => $userId],
            'gurus' => User::query()
                ->where('role', Role::Guru)
                ->orderBy('name')
                ->get(['id', 'name']),
            'rekap' => $rekapBulanan($tahun, $bulan, $userId),
        ]);
    }

    /**
     * Export CSV tanpa package tambahan: streamDownload + fputcsv. Excel dan
     * LibreOffice membuka CSV tanpa masalah.
     */
    public function export(Request $request, RekapBulanan $rekapBulanan): StreamedResponse
    {
        [$tahun, $bulan, $userId] = $this->filter($request);

        $rekap = $rekapBulanan($tahun, $bulan, $userId);
        $nama = sprintf('rekap-absensi-%04d-%02d.csv', $tahun, $bulan);

        return response()->streamDownload(function () use ($rekap): void {
            $keluaran = fopen('php://output', 'wb');

            // BOM supaya Excel membaca UTF-8 dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['NIP', 'Nama', ...$rekap['tanggals']]);

            foreach ($rekap['baris'] as $baris) {
                fputcsv($keluaran, [
                    $baris['nip'] ?? '',
                    $baris['nama'],
                    ...array_map(fn (array $hari): string => $hari['label'], $baris['hari']),
                ]);
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: int, 1: int, 2: int|null}
     */
    private function filter(Request $request): array
    {
        $data = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2020,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return [
            (int) ($data['tahun'] ?? now()->year),
            (int) ($data['bulan'] ?? now()->month),
            isset($data['user_id']) ? (int) $data['user_id'] : null,
        ];
    }
}
```

- [ ] **Step 5: Register the routes**

Dalam group admin:

```php
        Route::get('rekap', [Admin\RekapController::class, 'index'])->name('rekap.index');
        Route::get('rekap/export', [Admin\RekapController::class, 'export'])->name('rekap.export');
```

Route `rekap/export` harus didaftarkan **setelah** `rekap` tapi keduanya statis, jadi urutan tidak menimbulkan tabrakan.

- [ ] **Step 6: Add the shadcn table component**

```bash
npx shadcn-svelte@latest add table
```

- [ ] **Step 7: Write the Svelte page**

```svelte
<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Rekap', href: '/admin/rekap' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';

    type Hari = {
        tanggal: string;
        status: string;
        label: string;
        anomali: string[];
    };

    type Baris = {
        user_id: number;
        nama: string;
        nip: string | null;
        hari: Hari[];
        ringkasan: Record<string, number>;
    };

    let {
        filter,
        gurus,
        rekap,
    }: {
        filter: { tahun: number; bulan: number; user_id: number | null };
        gurus: { id: number; name: string }[];
        rekap: { tanggals: string[]; baris: Baris[] };
    } = $props();

    let tahun = $state(filter.tahun);
    let bulan = $state(filter.bulan);
    let guruId = $state<number | null>(filter.user_id);

    const namaBulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /** Kelas warna per status; ringkas, satu sumber. */
    const warna: Record<string, string> = {
        hadir: 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]',
        terlambat: 'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink)]',
        alfa: 'bg-[var(--g-red-c)] text-[var(--g-red-ink)]',
        izin: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        sakit: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        cuti: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
    };

    function terapkan(): void {
        router.get(
            '/admin/rekap',
            { tahun, bulan, user_id: guruId ?? undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function unduh(): void {
        const params = new URLSearchParams({ tahun: String(tahun), bulan: String(bulan) });

        if (guruId) {
            params.set('user_id', String(guruId));
        }

        window.location.href = `/admin/rekap/export?${params.toString()}`;
    }

    function judulAnomali(hari: Hari): string {
        return hari.anomali.length > 0 ? `${hari.label} · ${hari.anomali.join(', ')}` : hari.label;
    }
</script>

<AppHead title="Rekap absensi" />

<div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <h3>Rekap {namaBulan[bulan - 1]} {tahun}</h3>

        <div class="flex flex-wrap items-end gap-2">
            <select bind:value={bulan} class="h-10 rounded-md border border-input bg-background px-3">
                {#each namaBulan as nama, index (nama)}
                    <option value={index + 1}>{nama}</option>
                {/each}
            </select>

            <input
                type="number"
                bind:value={tahun}
                class="h-10 w-24 rounded-md border border-input bg-background px-3"
            />

            <select bind:value={guruId} class="h-10 rounded-md border border-input bg-background px-3">
                <option value={null}>Semua guru</option>
                {#each gurus as guru (guru.id)}
                    <option value={guru.id}>{guru.name}</option>
                {/each}
            </select>

            <Button onclick={terapkan}>Terapkan</Button>
            <Button variant="outline" onclick={unduh}>
                <Download class="size-4" aria-hidden="true" />
                CSV
            </Button>
        </div>
    </section>

    <section class="g-tile g-tone-plain">
        <!-- Tabel lebar harus menggulir di dalam wadahnya sendiri, bukan menggeser halaman. -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-card px-2 py-2 text-left">Guru</th>
                        {#each rekap.tanggals as tanggal (tanggal)}
                            <th class="px-1 py-2 text-center font-medium text-muted-foreground">
                                {Number(tanggal.slice(-2))}
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each rekap.baris as baris (baris.user_id)}
                        <tr class="border-t border-border">
                            <th class="sticky left-0 bg-card px-2 py-2 text-left font-medium">
                                {baris.nama}
                                {#if baris.nip}
                                    <span class="block text-xs text-muted-foreground">{baris.nip}</span>
                                {/if}
                            </th>
                            {#each baris.hari as hari (hari.tanggal)}
                                <td class="px-1 py-1 text-center">
                                    <span
                                        class="inline-flex size-7 items-center justify-center rounded-lg text-xs font-semibold {warna[hari.status] ?? 'text-muted-foreground'}"
                                        title={judulAnomali(hari)}
                                    >
                                        {hari.label.slice(0, 1)}
                                        {#if hari.anomali.includes('koordinat_kembar')}
                                            <span class="sr-only">koordinat kembar</span>
                                            !
                                        {/if}
                                    </span>
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Ringkasan</h3>
        <ul class="divide-y divide-border">
            {#each rekap.baris as baris (baris.user_id)}
                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span class="font-medium">{baris.nama}</span>
                    <span class="text-muted-foreground">
                        Hadir {baris.ringkasan.hadir ?? 0} ·
                        Terlambat {baris.ringkasan.terlambat ?? 0} ·
                        Alfa {baris.ringkasan.alfa ?? 0} ·
                        Izin {(baris.ringkasan.izin ?? 0) + (baris.ringkasan.sakit ?? 0) + (baris.ringkasan.cuti ?? 0)}
                    </span>
                </li>
            {/each}
        </ul>
    </section>
</div>
```

- [ ] **Step 8: Run tests, quality gates, commit**

```bash
php artisan test --compact tests/Feature/Admin/RekapTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan wayfinder:generate
npm run build
git add -A
git commit -m "feat: monthly attendance report with anomaly flags and CSV export"
```

Expected: PASS, 13 tests.

---

## Task 15: PWA standalone di Android dan iPhone

Sasaran: setelah dipasang ke home screen, **tidak ada address bar** di kedua OS, dan tombol TAP tidak tertutup notch atau home indicator.

`public/sw.js` **tidak diubah**. Yang sekarang sudah benar: hanya menangani `GET`, jadi `POST /absensi` tidak pernah tersentuh cache. Jangan menambah background sync — antrean tap offline ditolak permanen.

**Files:**
- Modify: `public/manifest.webmanifest`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/css/app.css`
- Create: `resources/js/components/InstallPrompt.svelte`
- Test: `tests/Feature/PwaTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `InstallPrompt.svelte` tanpa props; dipakai di `Dashboard.svelte` (Task 9).

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/PwaTest.php

test('manifest menyatakan mode standalone dan start url dashboard', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/dashboard')
        ->and($manifest['orientation'])->toBe('portrait')
        ->and($manifest['short_name'])->toBe('Absensi');
});

test('halaman memuat meta yang menghilangkan address bar di iOS', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('mobile-web-app-capable', false)
        ->assertSee('apple-mobile-web-app-status-bar-style', false)
        ->assertSee('viewport-fit=cover', false);
});

test('halaman memuat theme color untuk mode terang dan gelap', function () {
    $this->get(route('login'))
        ->assertSee('prefers-color-scheme: light', false)
        ->assertSee('prefers-color-scheme: dark', false);
});

test('service worker tetap hanya menangani GET', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("request.method !== 'GET'")
        ->and($sw)->not->toContain('sync');
});
```

Test terakhir adalah pengaman aturan, bukan uji fungsi. Kalau seseorang menambah background sync nanti, test ini yang menjelaskan kenapa itu dilarang.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/PwaTest.php`
Expected: FAIL — `start_url` masih `/`, meta Apple belum ada.

- [ ] **Step 3: Rewrite the manifest**

```json
{
    "name": "Absensi Syekh Yusuf",
    "short_name": "Absensi",
    "start_url": "/dashboard",
    "scope": "/",
    "display": "standalone",
    "display_override": ["standalone"],
    "orientation": "portrait",
    "background_color": "#ffffff",
    "theme_color": "#ffffff",
    "icons": [
        { "src": "/pwa-192.png", "sizes": "192x192", "type": "image/png" },
        { "src": "/pwa-512.png", "sizes": "512x512", "type": "image/png" },
        { "src": "/pwa-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
    ]
}
```

`display: standalone` menghilangkan address bar di Android Chrome dan iOS 16.4+. Sengaja **bukan** `fullscreen`: itu menyembunyikan status bar berisi jam, dan guru justru butuh melihat jam saat absen.

`start_url: /dashboard` membuat app terbuka langsung di halaman tap. Kalau sesi habis, Laravel mengalihkan ke login — perilaku yang benar.

- [ ] **Step 4: Update the head in app.blade.php**

Ganti baris `<meta name="viewport" ...>` dan `<meta name="theme-color" ...>` yang sudah ada dengan blok berikut; sisa head tidak diubah:

```html
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Absensi">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
```

`viewport-fit=cover` wajib agar `env(safe-area-inset-*)` punya nilai di iPhone. iOS versi lama tidak membaca manifest, jadi meta Apple tetap perlu meski manifest sudah benar.

`status-bar-style: default` dipilih daripada `black-translucent`: konten tidak merangkak ke bawah status bar, jadi nol perhitungan safe-area di sisi atas, dan status bar mengikuti `theme-color` terang/gelap otomatis.

- [ ] **Step 5: Add the mobile CSS rules**

Tambahkan di akhir `resources/css/app.css`:

```css
@layer base {
    html {
        -webkit-text-size-adjust: 100%;
    }

    body {
        /* Matikan bounce dan pull-to-refresh iOS: keduanya terasa seperti bug
           di app yang terpasang. */
        overscroll-behavior-y: none;
        -webkit-tap-highlight-color: transparent;
    }

    /* iOS auto-zoom saat fokus ke input yang font-nya di bawah 16px. */
    input,
    select,
    textarea {
        font-size: max(1rem, 16px);
    }
}

@layer utilities {
    /* 100vh salah di mobile saat toolbar muncul-hilang; dvh mengikuti. */
    .app-viewport {
        min-height: 100dvh;
    }

    /* Tombol TAP tidak boleh tertutup home indicator iPhone. */
    .safe-bottom {
        padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
    }

    .safe-top {
        padding-top: env(safe-area-inset-top);
    }
}
```

- [ ] **Step 6: Write InstallPrompt.svelte**

```svelte
<!-- resources/js/components/InstallPrompt.svelte -->
<script lang="ts">
    import Share from 'lucide-svelte/icons/share';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import X from 'lucide-svelte/icons/x';
    import { Button } from '@/components/ui/button';

    type PromptInstall = Event & {
        prompt: () => Promise<void>;
        userChoice: Promise<{ outcome: string }>;
    };

    let terpasang = $state(true);
    let promptAndroid = $state<PromptInstall | null>(null);
    let iOS = $state(false);
    let ditutup = $state(false);

    $effect(() => {
        // Sudah berjalan sebagai app terpasang? Tidak perlu banner.
        const standalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as Navigator & { standalone?: boolean }).standalone === true;

        terpasang = standalone;

        iOS =
            /iphone|ipad|ipod/i.test(navigator.userAgent) &&
            !('onbeforeinstallprompt' in window);

        const tangkap = (event: Event) => {
            event.preventDefault();
            promptAndroid = event as PromptInstall;
        };

        window.addEventListener('beforeinstallprompt', tangkap);

        return () => window.removeEventListener('beforeinstallprompt', tangkap);
    });

    const tampil = $derived(!terpasang && !ditutup && (promptAndroid !== null || iOS));

    async function pasang(): Promise<void> {
        if (!promptAndroid) {
            return;
        }

        await promptAndroid.prompt();
        promptAndroid = null;
    }
</script>

{#if tampil}
    <!-- Selama dibuka lewat tab browser, address bar tetap ada dan start_url tidak
         berlaku. Tanpa banner ini sebagian guru tidak akan pernah memasangnya. -->
    <aside class="g-tile g-tone-blue gap-3">
        <div class="flex items-start justify-between gap-3">
            <h3 class="flex items-center gap-2">
                <Smartphone class="size-5" aria-hidden="true" />
                Pasang aplikasi
            </h3>
            <button
                type="button"
                onclick={() => (ditutup = true)}
                aria-label="Tutup"
                class="opacity-70"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>

        {#if promptAndroid}
            <p>Pasang ke layar utama supaya absen bisa dibuka sekali ketuk, tanpa address bar.</p>
            <Button onclick={pasang}>Pasang sekarang</Button>
        {:else}
            <p class="flex items-center gap-2">
                Ketuk
                <Share class="size-4" aria-hidden="true" />
                <strong>Bagikan</strong>
                lalu pilih
                <strong>Tambahkan ke Layar Utama</strong>.
            </p>
        {/if}
    </aside>
{/if}
```

- [ ] **Step 7: Wire it into Dashboard.svelte**

Hapus komentar pada import dan tag `<InstallPrompt />` yang dipasang di Task 9.

- [ ] **Step 8: Run tests, build, commit**

```bash
php artisan test --compact tests/Feature/PwaTest.php
npm run build
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: standalone PWA tuning for Android and iOS"
```

Expected: PASS, 4 tests.

- [ ] **Step 9: Verify on real devices**

Test otomatis tidak bisa membuktikan address bar hilang. Cek manual, dan jangan tandai task ini selesai sebelum keduanya lolos:

- **Android Chrome:** buka app → banner "Pasang sekarang" muncul → pasang → buka dari home screen → tidak ada address bar, orientasi terkunci portrait.
- **iPhone Safari:** buka app → instruksi Bagikan muncul → Tambahkan ke Layar Utama → buka dari home screen → tidak ada address bar, tombol TAP tidak tertutup home indicator, fokus ke input tidak memicu zoom.

---

## Task 16: Navigasi, penutup, dan checklist rilis

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/components/AppSidebar.svelte`
- Modify: `resources/js/pages/settings/Security.svelte`
- Create: `tests/Feature/NavigasiTest.php`

**Interfaces:**
- Consumes: `Role`, gate `admin`, semua route dari task sebelumnya.
- Produces: shared prop `auth.isAdmin` (bool) untuk seluruh halaman Inertia.

- [ ] **Step 1: Write the failing test**

```php
<?php

// tests/Feature/NavigasiTest.php

use App\Models\User;

test('guru mendapat flag isAdmin false', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isAdmin', false));
});

test('admin mendapat flag isAdmin true', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isAdmin', true));
});

test('semua route admin tertutup untuk guru', function (string $nama, array $parameter) {
    $this->actingAs(User::factory()->create())
        ->get(route($nama, $parameter))
        ->assertForbidden();
})->with([
    ['admin.rekap.index', []],
    ['admin.izin.index', []],
    ['admin.guru.index', []],
    ['admin.pengaturan.edit', []],
]);

test('perangkat terikat muncul di halaman keamanan', function () {
    [$guru, $perangkat] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('perangkats', 1));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/NavigasiTest.php`
Expected: FAIL — `auth.isAdmin` belum dibagikan.

- [ ] **Step 3: Share the admin flag**

Di `app/Http/Middleware/HandleInertiaRequests.php`, ubah blok `auth`:

```php
            'auth' => [
                'user' => $request->user(),
                // Flag ini hanya untuk menyembunyikan menu. Keputusan sebenarnya
                // tetap di gate 'admin' pada route group.
                'isAdmin' => (bool) $request->user()?->can('admin'),
            ],
```

- [ ] **Step 4: Add the device section to the security page**

Di `app/Http/Controllers/Settings/SecurityController.php`, tambahkan prop pada `edit()`:

```php
            'perangkats' => $request->user()->perangkats()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Perangkat $perangkat): array => [
                    'id' => $perangkat->id,
                    'label' => $perangkat->label,
                    'status' => $perangkat->status->value,
                    'terdaftar' => $perangkat->created_at?->format('d M Y'),
                ])
                ->all(),
```

Import `use App\Models\Perangkat;`.

Di `resources/js/pages/settings/Security.svelte`, tambahkan section di samping `ManagePasskeys` yang sudah ada:

```svelte
<div class="space-y-6">
    <Heading
        variant="small"
        title="Perangkat absensi"
        description="HP yang terikat ke akunmu untuk absen"
    />

    <ul class="divide-y divide-border overflow-hidden rounded-lg border border-border">
        {#each perangkats as perangkat (perangkat.id)}
            <li class="flex items-center justify-between gap-3 p-4 text-sm">
                <span>
                    <span class="font-medium">{perangkat.label}</span>
                    <span class="block text-muted-foreground">
                        Terdaftar {perangkat.terdaftar ?? '-'}
                    </span>
                </span>
                <Badge variant={perangkat.status === 'active' ? 'default' : 'outline'}>
                    {perangkat.status}
                </Badge>
            </li>
        {/each}
    </ul>

    <p class="text-sm text-muted-foreground">
        Ganti HP? Buka aplikasi dari HP baru sekali, lalu minta TU menyetujuinya.
        Satu guru hanya boleh punya satu HP aktif.
    </p>
</div>
```

Tambahkan `perangkats` ke `$props()` halaman itu dan import `Badge`.

- [ ] **Step 5: Update the sidebar**

Di `resources/js/components/AppSidebar.svelte`:

```ts
    import { page } from '@inertiajs/svelte';
    import CalendarOff from 'lucide-svelte/icons/calendar-off';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import Settings2 from 'lucide-svelte/icons/settings-2';
    import Table2 from 'lucide-svelte/icons/table-2';
    import Users from 'lucide-svelte/icons/users';

    const isAdmin = $derived(page.props.auth.isAdmin === true);

    const mainNavItems: NavItem[] = [
        { title: 'Absensi', href: dashboard(), icon: Fingerprint },
        { title: 'Izin', href: '/izin', icon: CalendarOff },
    ];

    const adminNavItems: NavItem[] = [
        { title: 'Rekap', href: '/admin/rekap', icon: Table2 },
        { title: 'Izin masuk', href: '/admin/izin', icon: CalendarOff },
        { title: 'Guru', href: '/admin/guru', icon: Users },
        { title: 'Pengaturan', href: '/admin/pengaturan', icon: Settings2 },
    ];
```

Di bagian markup, di dalam `SidebarContent`:

```svelte
        <NavMain items={mainNavItems} label="ABSENSI" />
        {#if isAdmin}
            <NavMain items={adminNavItems} label="ADMIN" />
        {/if}
        <NavMain items={settingsNavItems} label="PENGATURAN" />
```

Hapus item `Dashboard` yang lama dan ikon `LayoutGrid` kalau tidak lagi terpakai — jangan tinggalkan import mati.

- [ ] **Step 6: Run the whole suite**

```bash
php artisan test --compact
```

Expected: seluruh suite hijau. Jumlah kasar: 6 + 4 + 8 + 5 + 4 + 5 + 7 + 21 + 5 + 10 + 6 + 8 + 8 + 13 + 4 + 7 ≈ 121 test.

- [ ] **Step 7: Final quality gates**

```bash
vendor/bin/pint --format agent
vendor/bin/phpstan analyse
npm run build
npx svelte-check
```

`svelte-check` boleh mengeluarkan peringatan dari starter kit yang sudah ada sebelumnya; yang tidak boleh adalah error baru dari file yang dibuat plan ini.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: navigation, bound-device section, and admin menu"
```

- [ ] **Step 9: Checklist rilis — verifikasi manual, wajib**

Hal-hal berikut **tidak bisa** dibuktikan oleh test otomatis. Jangan menyatakan aplikasi siap dipakai sebelum semuanya dicoba di perangkat nyata.

- [ ] `APP_URL` sudah HTTPS, dan aplikasi benar-benar diakses lewat HTTPS. Tanpa itu `navigator.geolocation` mati dan seluruh app tidak jalan.
- [ ] `config('app.timezone')` bernilai `Asia/Jakarta` di server produksi.
- [ ] Koordinat sekolah asli sudah dimasukkan di `/admin/pengaturan` (pakai tombol "Pakai lokasi saya" sambil berdiri di gerbang), radius diuji dengan berjalan ke batasnya.
- [ ] Jadwal kerja asli sudah diisi, termasuk hari yang bukan hari kerja.
- [ ] Upacara sidik jari benar-benar berjalan: daftarkan passkey di `/settings/security` dari HP nyata, lalu tap — prompt biometrik harus muncul dan absen tercatat `terverifikasi = true`.
- [ ] Guru berpasskey yang membatalkan prompt biometrik **gagal** absen.
- [ ] Android: banner install muncul, app terpasang tanpa address bar.
- [ ] iPhone: instruksi Bagikan muncul, app terpasang tanpa address bar, tombol TAP tidak tertutup home indicator.
- [ ] iPhone: izin lokasi diminta ulang setelah app dipasang, dan **Lokasi Tepat** aktif. Dengan lokasi kabur, tap harus ditolak dengan pesan yang menyebut "Lokasi Tepat".
- [ ] Coba absen dari luar sekolah: harus ditolak, dan barisnya muncul di `absensi_attempts` dengan `hasil = luar_radius`.
- [ ] Coba absen pakai HP rekan: harus ditolak `perangkat_asing`.
- [ ] Matikan data seluler lalu tap: tombol menolak dengan pesan "butuh koneksi", **tidak** ada antrean yang tersimpan.
- [ ] Export CSV terbuka benar di Excel, termasuk nama guru berhuruf non-ASCII.

---

## Catatan penyimpangan dari spec

Tiga hal diputuskan saat menyusun plan ini, semuanya sudah dijelaskan di task masing-masing:

1. **Passkey memakai step-up berbasis session**, bukan assertion di payload tap (Task 8). Alasan: `PasskeyVerificationRequest` meng-hardcode session key `passkey.verification_options`, dan `usePasskeyVerify` sudah menangani seluruh upacara WebAuthn. Efek samping yang menguntungkan: lapisan biometrik jadi bisa diuji otomatis.
2. **`HasilTap` mendapat dua nilai tambahan**, `PasskeyInvalid` dan `BelumMasuk` (Task 6). Spec tidak menentukan hasil apa yang dicatat saat verifikasi gagal, dan tidak menentukan perilaku tap pulang tanpa tap masuk.
3. **Alfa dihitung untuk `tanggal < hari ini`**, bukan `≤ hari ini` (Task 14). Membaca spec apa adanya membuat semua guru terlihat Alfa sepanjang pagi.
