# Absensi Guru Berbasis Geolokasi — Design

Tanggal: 2026-09-03
Status: disetujui, siap masuk tahap implementation plan

## 1. Tujuan

Sistem absensi guru untuk satu sekolah. Guru tap tombol di HP pribadinya; sistem
mencatat kehadiran hanya jika guru benar-benar berada di lokasi sekolah. Tidak ada
selfie. Sasaran utama: **guru tidak bisa menitipkan absen ke rekan.**

Bukan tujuan Fase 1: absensi siswa, payroll, multi-yayasan, aplikasi native.

## 2. Keputusan yang sudah diambil

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Perangkat | HP pribadi guru | Memungkinkan device-binding + biometrik. Tanpa belanja hardware. |
| Bentuk app | PWA (install dari browser) | Satu codebase, update instan, nol biaya distribusi. |
| Cakupan | Standalone, satu sekolah | Tanpa `yayasan_id`/`sekolah_id`. Tambah kolom nanti kalau digabung ke yayasan-platform. |
| Inti anti-titip | Geofence server-side + device-binding + passkey | Biaya nyontek tinggi tanpa hardware tambahan. |
| Guru tanpa biometrik | Tetap boleh absen | Ditandai `terverifikasi = false`, muncul sebagai anomali di rekap. Tidak memblokir siapa pun. |

Alternatif yang ditolak, beserta alasannya:

- **QR berputar di gerbang** — lebih kuat lawan fake GPS, tapi butuh perangkat display,
  listrik, dan penjaga. Rusak = seluruh sekolah tidak bisa absen. Disimpan sebagai lapisan
  tambahan, dipasang **hanya kalau** tabel `absensi_attempts` membuktikan ada yang pakai fake GPS.
- **Tag NFC di dinding** — Web NFC hanya jalan di Chrome Android; guru iPhone langsung mati.
  Tag statis juga bisa dikloning.
- **Native Android** — bisa deteksi mock-location, tapi butuh codebase kedua, akun Play
  Store, dan guru harus update manual. Ditunda sampai ada bukti kecurangan.

## 3. Stack yang sudah ada di repo

Laravel + Fortify + Inertia + **Svelte 5** + Tailwind 4 + shadcn-svelte (`components/ui/`)
+ Wayfinder + `laravel/passkeys` + `@laravel/passkeys`. DB: SQLite. PHPStan level 7. Pest.

PWA sudah berjalan lewat file statis buatan tangan di `public/`
(`manifest.webmanifest`, `sw.js`, `offline.html`, ikon 192/512/apple-touch), didaftarkan
di `resources/views/app.blade.php`. `vite-plugin-pwa` ada di `devDependencies` tapi
**tidak dipakai** — biarkan, atau hapus terpisah dari pekerjaan ini.

Tidak ada dependency baru. Satu tambahan komponen generator resmi:
`npx shadcn-svelte@latest add table`.

## 4. Prasyarat yang harus dibetulkan lebih dulu

Dua hal ini mendahului semua fitur. Kalau kelewat, seluruh rekap salah dan sulit dilacak.

1. **`config/app.php` → `'timezone' => 'Asia/Jakarta'`** (sekarang `UTC`).
   Guru tap 07:00 WIB tercatat 00:00 UTC; `unique(user_id, tanggal)` berpindah hari di
   tengah pagi dan status `terlambat` salah hitung.
2. **Produksi wajib HTTPS.** `navigator.geolocation` mati di HTTP non-localhost. Dideploy
   di HTTP = app tidak jalan sama sekali.

## 5. Skema data

Nama model/tabel domain memakai bahasa Indonesia; `User` tetap dari starter kit.
Semua tabel domain memakai `$fillable` eksplisit — tidak ada `$guarded = []`.

### `users` (tambah kolom)

`nip` string nullable · `role` enum(`guru`,`admin`) default `guru` · `is_active` bool default true

### `perangkats`

Device binding. Satu tabel mengerjakan empat hal: mengikat HP, menampung permintaan ganti
HP, menyimpan jejak audit, dan mendeteksi satu HP dipakai dua akun.

`user_id` FK · `uuid` string **unique global** · `label` string · `user_agent` text ·
`status` enum(`pending`,`active`,`revoked`) · `approved_by` FK nullable ·
`approved_at` nullable · timestamps

**`uuid` unique global, bukan unique per user.** Itu yang membuat HP yang sudah dipakai
guru A ditolak saat didaftarkan ke guru B — pola titip absen paling umum.

### `lokasis`

`nama` · `latitude` decimal(10,7) · `longitude` decimal(10,7) · `radius_meter` int default 100 ·
`is_active` bool

Tabel, bukan config, supaya admin bisa memperbaiki koordinat tanpa deploy.

### `jadwal_kerjas`

Tujuh baris, satu per hari.

`day_of_week` tinyint 0-6 (unique) · `jam_masuk` time · `jam_pulang` time ·
`toleransi_menit` int · `is_hari_kerja` bool

Seeder: Senin–Sabtu 07:00–14:00 toleransi 10 menit; Minggu `is_hari_kerja = false`.

### `hari_liburs`

`tanggal` date unique · `nama` string

### `absensi_attempts`

**Setiap** tap dicatat di sini — diterima maupun ditolak. Ini sumber data audit anomali;
tanpa tabel ini, keputusan "perlu QR gerbang atau tidak" hanya tebakan.

`user_id` FK · `tipe` enum(`masuk`,`pulang`) · `latitude` decimal(10,7) ·
`longitude` decimal(10,7) · `accuracy_meter` int · `jarak_meter` int nullable ·
`lokasi_id` FK nullable · `perangkat_uuid` string · `terverifikasi` bool ·
`hasil` enum(`diterima`,`luar_radius`,`akurasi_buruk`,`perangkat_asing`,`duplikat`,`passkey_invalid`) ·
`created_at`

Index: (`user_id`, `created_at`), (`hasil`, `created_at`).

### `absensis`

Ringkasan, satu baris per guru per hari. Geo tidak diduplikasi — cukup FK ke attempt.

`user_id` FK · `tanggal` date · `masuk_attempt_id` FK nullable ·
`pulang_attempt_id` FK nullable · `status` enum(`hadir`,`terlambat`) ·
`pulang_cepat` bool · timestamps · **unique(`user_id`, `tanggal`)**

Alfa tidak disimpan — dihitung saat rekap dari tidak adanya baris.

### `izins`

`user_id` FK · `tipe` enum(`izin`,`sakit`,`cuti`) · `tanggal_mulai` date ·
`tanggal_selesai` date · `alasan` text · `lampiran_path` string nullable ·
`status` enum(`pending`,`disetujui`,`ditolak`) · `reviewed_by` FK nullable ·
`reviewed_at` nullable · `catatan_review` text nullable · timestamps

Tujuh tabel baru. Sengaja **tidak** dibuat: tabel shift, tabel role/permission (dua role
cukup satu kolom), tabel notifikasi, tabel kuota cuti tahunan.

## 6. Alur tap

### Klien — `resources/js/pages/Dashboard.svelte`

Klien **tidak** menghitung jarak, **tidak** menentukan status, **tidak** menentukan
`terverifikasi`. Semua diputuskan server.

1. Guru tekan tombol TAP MASUK / TAP PULANG
2. `navigator.geolocation.getCurrentPosition({ enableHighAccuracy: true, timeout: 10000 })`
3. `device_uuid` dibaca dari `localStorage`; kalau kosong, baca dari cookie pemulihan
   (lihat §9); kalau dua-duanya kosong, `crypto.randomUUID()` lalu simpan
4. Kalau `Passkeys.isSupported()` dan guru punya passkey terdaftar:
   `GET /absensi/passkey-options` → `navigator.credentials.get()` → dapat assertion
5. `POST /absensi` dengan `{ tipe, latitude, longitude, accuracy, device_uuid, credential? }`

### Server — `app/Actions/Absensi/CatatAbsensi.php`, dipanggil `AbsensiController@store`

Urutan gerbang. Setiap kegagalan **tetap menulis baris `absensi_attempts`** lalu balas 422:

| Gerbang | `hasil` saat gagal | Pesan ke guru |
|---|---|---|
| FormRequest: `latitude` −90..90, `longitude` −180..180, `accuracy` numeric ≥ 0, `device_uuid` format uuid | — (422 validasi) | — |
| Throttle 10 tap/menit/user | — (429) | "Terlalu banyak percobaan, tunggu sebentar." |
| `perangkats` cocok `user_id` + `status = active` | `perangkat_asing` | "HP ini belum terdaftar untuk akunmu. Hubungi TU." |
| `accuracy_meter <= 75` | `akurasi_buruk` | "Sinyal GPS lemah (±120 m). Coba di luar ruangan." |
| Haversine ke tiap `lokasis` aktif, ambil terdekat, `jarak_meter <= radius_meter` | `luar_radius` | "Kamu 340 m dari sekolah. Absen hanya dalam 100 m." |
| Belum ada tap `tipe` sama hari ini | `duplikat` | "Sudah absen masuk 07:02." |
| Kalau `credential` dikirim: `VerifyPasskey` lolos | `passkey_invalid` | "Verifikasi sidik jari gagal." |

Lolos semua → tulis attempt `diterima`, lalu `upsert` `absensis`:

- `status` = `terlambat` jika `jam tap masuk > jam_masuk + toleransi_menit`, selain itu `hadir`
- `pulang_cepat` = `jam tap pulang < jam_pulang`
- `terverifikasi` disimpan di **`absensi_attempts`**, bukan di `absensis`. Rekap membacanya
  lewat relasi `masuk_attempt_id`. Tidak ada duplikasi kolom.

Haversine ditulis sebagai fungsi murni di `app/Support/Jarak.php` supaya bisa diuji unit.

### Passkey step-up

Tidak perlu dependency baru. Pakai yang sudah ada di `vendor/laravel/passkeys/src`:

- `GET /absensi/passkey-options` → `Actions\GenerateVerificationOptions`, simpan hasil
  serialisasi di session dengan key milik sendiri (`absensi.passkey_options`)
- `POST /absensi` → `Actions\VerifyPasskey` dengan credential dari request

**Catatan keamanan yang menentukan seluruh desain:** passkey bisa sinkron antar perangkat
(iCloud Keychain, Google Password Manager). Jadi passkey **sendirian bukan** bukti
perangkat. Dua lapis dipakai bersama: `device_uuid` (per-install, tidak sinkron)
membuktikan *perangkat*; passkey membuktikan *orang*. Menghapus salah satunya
meruntuhkan jaminan anti-titip.

### Pendaftaran perangkat

HP **pertama** milik seorang guru auto-approve (`status = active`) supaya rollout tidak
macet di meja TU. HP **kedua dan seterusnya** masuk `pending` dan butuh approve admin.
Jalur ganti HP itulah vektor titip absen, jadi hanya itu yang dijaga.

## 7. Izin, kalender kerja, aturan rekap

### Izin

- Guru: `/izin` — daftar pengajuan sendiri + form. Tanggal pakai `<input type="date">`
  bawaan browser, tanpa library calendar.
- Lampiran opsional: validasi mime `pdf,jpg,png`, maks 2 MB, disimpan di disk **`local`**
  (`storage/app/private/izin`), **bukan** `public`. Diakses lewat route bertanda tangan +
  `IzinPolicy`, supaya surat dokter guru A tidak bisa dibuka guru B dengan menebak URL.
- Tolak pengajuan yang tanggalnya tumpang tindih dengan izin `pending`/`disetujui`
  milik guru yang sama.
- Admin: `/admin/izin` — daftar pending, approve/reject + catatan.

### Kalender kerja

- `/admin/pengaturan` kartu Jadwal — edit tujuh baris `jadwal_kerjas`
- `/admin/pengaturan` kartu Hari libur — CRUD `tanggal` + `nama`

### Aturan status harian — `app/Support/StatusHarian.php`

Fungsi murni, tanpa query. Ini yang menentukan benar-tidaknya seluruh rekap. Dievaluasi
per (guru, tanggal), berhenti di kecocokan pertama:

1. `jadwal_kerjas.is_hari_kerja = false` → `-` (bukan hari kerja)
2. tanggal ada di `hari_liburs` → `Libur`
3. ada `izins` berstatus `disetujui` yang mencakup tanggal → `Izin` / `Sakit` / `Cuti`
4. ada baris `absensis` → `Hadir` atau `Terlambat`, plus penanda `pulang cepat` dan
   `belum tap pulang`
5. tidak ada apa pun dan tanggal ≤ hari ini → `Alfa`

## 8. UI/UX

Ikut yang sudah ada: `AppLayout` + `AppSidebarLayout`, komponen `ui/` (Card, Button,
Badge, Dialog, Select, Input, Alert, Table), toast `svelte-sonner`, ikon `lucide-svelte`,
font Roboto Flex yang sudah dimuat. Tidak ada library UI baru, tidak ada konvensi folder baru.

### Halaman guru

- **`/dashboard`** — rombak `Dashboard.svelte` yang sekarang masih placeholder. Isi: jam
  berjalan, jadwal hari ini, kartu status hari ini, satu tombol besar TAP MASUK/TAP PULANG,
  riwayat 30 hari di bawahnya. Riwayat tidak dapat halaman sendiri.
- **`/izin`** — daftar pengajuan sendiri + form.
- **`/settings/security`** — tambah section "Perangkat absensi terikat" (label HP, tanggal
  ikat, tombol ajukan ganti HP) di halaman yang sudah ada, bersebelahan dengan
  `ManagePasskeys` yang sudah berjalan. Passkey tidak perlu UI baru.

### Halaman admin — empat saja

- **`/admin/rekap`** — filter bulan + guru, tabel status harian, badge anomali, export CSV
- **`/admin/izin`** — approve/reject
- **`/admin/guru`** — buat akun guru manual, aktif/nonaktif, approve permintaan ganti HP
- **`/admin/pengaturan`** — satu halaman tiga kartu: Lokasi, Jadwal kerja, Hari libur

Sidebar `AppSidebar.svelte` diedit: menu admin muncul bersyarat dari `role` di shared
props `HandleInertiaRequests`.

### Export

`response()->streamDownload()` + `fputcsv`. Tanpa package Excel — Excel membuka CSV tanpa
masalah, dan itu satu dependency yang tidak perlu diurus.

### Badge anomali di rekap

Dihitung saat query, tanpa cron:

- `masuk_attempt.terverifikasi = false` → badge kuning "tanpa biometrik"
- koordinat persis sama (7 desimal) dengan guru lain di hari yang sama → badge merah
  "koordinat kembar"

"Impossible travel" belum dibuat — butuh data historis yang belum ada. Tabel
`absensi_attempts` memang dibuat supaya keputusan lapisan berikutnya berdasar bukti.

### Otorisasi

Kolom `users.role`, `Gate::define('admin')`, route group admin pakai middleware
`can:admin`. Plus `IzinPolicy` supaya guru hanya melihat izin miliknya sendiri. Tombol
admin disembunyikan lewat shared props Inertia, tapi keputusan sebenarnya di server —
UI menyembunyikan, gate yang menolak.

## 9. PWA fullscreen — Android dan iPhone

Target: setelah dipasang ke home screen, **tidak ada address bar** di kedua OS, dan tombol
tap tidak tertutup notch atau home indicator.

### `public/manifest.webmanifest`

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

`start_url: /dashboard` supaya app terbuka langsung di halaman tap. Kalau sesi habis,
Laravel mengalihkan ke login — perilaku yang benar.

`display: standalone` sudah cukup untuk menghilangkan address bar di **Android Chrome**
dan di **iOS 16.4+**. Jangan pakai `fullscreen`: itu menyembunyikan status bar (jam,
baterai, sinyal), dan guru justru butuh melihat jam saat absen.

### `resources/views/app.blade.php` — tambahan head

iOS versi lama tidak membaca manifest, jadi meta Apple tetap wajib:

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Absensi">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
```

`viewport-fit=cover` diperlukan agar `env(safe-area-inset-*)` punya nilai di iPhone.
`status-bar-style: default` dipilih daripada `black-translucent` supaya konten tidak
merangkak ke bawah status bar — nol perhitungan safe-area di atas, dan status bar
otomatis mengikuti `theme-color` terang/gelap.

`<link rel="apple-touch-icon">` dan `<link rel="manifest">` sudah ada, tidak diubah.

### `resources/css/app.css` — aturan mobile

```css
@layer base {
    html { -webkit-text-size-adjust: 100%; }

    body {
        overscroll-behavior-y: none;      /* matikan bounce & pull-to-refresh iOS */
        -webkit-tap-highlight-color: transparent;
    }

    /* Tinggi viewport yang benar di mobile: 100vh salah saat toolbar muncul/hilang */
    .app-viewport { min-height: 100dvh; }

    /* Tombol tap tidak tertutup home indicator iPhone */
    .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
    .safe-top    { padding-top:    env(safe-area-inset-top); }

    /* iOS auto-zoom saat fokus input kalau font < 16px */
    input, select, textarea { font-size: max(1rem, 16px); }
}
```

Tombol TAP diberi `touch-action: manipulation` dan `select-none` supaya double-tap tidak
memicu zoom dan tahan-tekan tidak memunculkan menu seleksi teks.

### Banner install

Dua OS, dua perilaku, satu komponen `components/InstallPrompt.svelte`:

- Deteksi sudah terpasang: `window.matchMedia('(display-mode: standalone)').matches`
  **atau** `navigator.standalone === true` (khusus iOS). Kalau ya, banner tidak tampil.
- **Android**: tangkap event `beforeinstallprompt`, tampilkan tombol "Pasang aplikasi"
  yang memanggil `prompt()`.
- **iOS**: tidak ada API install. Tampilkan sheet instruksi bergambar ikon Share:
  "Ketuk **Bagikan** → **Tambahkan ke Layar Utama**".

Alasan banner ini bukan kemewahan: selama dibuka lewat tab browser biasa, address bar
tetap ada dan `start_url` tidak berlaku. Tanpa banner, sebagian guru tidak akan pernah
memasangnya.

### Izin lokasi di iOS standalone

PWA yang terpasang di home screen punya penyimpanan izin **terpisah** dari Safari. Guru
akan ditanya izin lokasi sekali lagi setelah memasang. Halaman dashboard harus menangani
`PERMISSION_DENIED` dengan pesan jelas: "Izin lokasi ditolak. Buka Pengaturan → Absensi →
Lokasi → Saat Menggunakan App, dan aktifkan Lokasi Tepat."

Minta juga **Precise Location**; kalau iOS memberi lokasi kabur, `accuracy` akan besar dan
gerbang `akurasi_buruk` menolaknya — jadi pesan errornya harus menyebut "Lokasi Tepat"
secara eksplisit, bukan hanya "aktifkan lokasi".

### Ketahanan `device_uuid`

`localStorage` bisa hilang: guru membersihkan data situs, atau iOS mengevakuasi
penyimpanan. Kalau `device_uuid` hilang, guru terlihat sebagai perangkat baru dan
terblokir menunggu approve admin — beban TU yang tidak perlu.

Mitigasi: saat perangkat terdaftar, server juga menaruh cookie `HttpOnly`, `Secure`,
`SameSite=Lax` bermasa 5 tahun berisi `device_uuid` bertanda tangan. Klien membaca
`localStorage` lebih dulu; kalau kosong, server memulihkan dari cookie dan mengirimkannya
kembali sebagai prop untuk ditulis ulang ke `localStorage`. Dua penyimpanan harus hilang
bersamaan sebelum guru terhalang.

Cookie ini **bukan** lapisan keamanan tambahan — nilainya sama dengan `localStorage`.
Fungsinya murni ketahanan.

### Aturan offline yang tidak bisa dinegosiasi

**Tidak ada antrean tap offline.** Kalau tap boleh disimpan offline lalu dikirim
belakangan, guru bisa mematikan data, memalsukan GPS, tap, lalu sinkron dari rumah — dan
server tidak punya cara membedakannya dari tap asli.

`public/sw.js` yang sekarang sudah benar: hanya menangani `request.method === 'GET'`, jadi
`POST /absensi` tidak pernah tersentuh cache. Jangan diubah menjadi background sync.
Offline = tombol tap mati + pesan "butuh koneksi".

## 10. Testing

Perintah: `php artisan test --compact`. Wajib lolos `vendor/bin/pint` dan
`vendor/bin/phpstan analyse` (level 7) tanpa error baru.

### Unit — fungsi murni, tanpa DB

- `StatusHarian`: lima cabang (bukan hari kerja, libur, izin, hadir/terlambat, alfa)
- `Jarak` (haversine): dua-tiga pasang koordinat dengan jarak yang sudah diketahui

### Feature — inti anti-titip, satu test per gerbang penolak

- tap dalam radius → baris `absensis` dibuat, attempt `diterima`
- tap 340 m dari lokasi → 422, attempt `luar_radius`, **tidak ada** baris `absensis`
- `accuracy` 120 m → 422, attempt `akurasi_buruk`
- `device_uuid` tidak terdaftar → 422, attempt `perangkat_asing`
- **HP guru A didaftarkan ke guru B → ditolak** (unique global). Ini test terpenting di
  seluruh suite — ini yang mematikan titip absen.
- tap masuk dua kali → 422, attempt `duplikat`
- tap lewat `jam_masuk + toleransi_menit` → status `terlambat`
- tap ke-11 dalam satu menit → 429

### Feature — otorisasi

- guru membuka `/admin/rekap` → 403
- guru membuka izin guru lain → 403
- guru mengunduh lampiran izin guru lain → 403

### Feature — rekap

- export CSV: header benar, baris sesuai bulan yang difilter

## 11. Yang sengaja tidak dibangun

Menolak daftar ini adalah keputusan sadar, bukan kelalaian:

- Selfie / face recognition — diminta tidak ada oleh pemilik proyek
- QR gerbang berputar — tunggu bukti fake GPS dari `absensi_attempts`
- Aplikasi native / Capacitor — tunggu bukti yang sama
- Notifikasi email/WA saat izin di-approve — flash message + badge dulu
- Kuota cuti tahunan, approval berjenjang, shift
- Multi-tenant (`yayasan_id`), absensi siswa, integrasi payroll
- Antrean tap offline — bukan ditunda, tapi **ditolak permanen** karena melubangi
  jaminan anti-titip
