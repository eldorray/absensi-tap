# Design

<!-- impeccable:design-schema 1 -->

## Visual world

**Material 3 Expressive, dialek hijau sekolah.** Dunia visual ini sudah hidup di
kode aplikasi dan diwarisi apa adanya, bukan diganti: permukaan tonal berlapis,
sudut sangat membulat yang berubah bentuk saat disentuh, satu warna utama hijau
daun, dan tipografi Roboto Flex yang rapat di ukuran besar.

Kenapa cocok: aplikasinya dipakai di halaman sekolah, di bawah matahari, dengan
satu tangan. Permukaan tonal memberi kontras tanpa garis tipis yang hilang di
layar silau, dan sudut besar membuat kartu terbaca sebagai objek yang bisa
disentuh, bukan tabel.

## Tokens

Sumber tunggal: `resources/css/app.css`. Jangan menulis hex di komponen.

- Warna utama `--g-blue` #2f6d21 (hijau daun), permukaan `--g-bg`/`--g-surface`,
  tinta `--g-ink`/`--g-ink-2`.
- Nada kartu: `.g-tone-plain`, `.g-tone-green`, `.g-tone-yellow`, `.g-tone-red`,
  `.g-tone-blue`. Nada dipakai untuk arti (hijau berhasil, kuning menunggu, merah
  ditolak), bukan hiasan.
- Radius: `--radius` 1rem untuk kontrol, 28px untuk kartu (`.g-tile`), 32-56px
  untuk permukaan besar.
- Bayangan `--g-shadow`, easing `--g-emphasized` cubic-bezier(0.2, 0, 0, 1).
- Gelap adalah warga kelas satu: setiap token punya pasangan di `.dark`.

## Type

- `Roboto Flex` untuk teks, `Roboto Mono` untuk angka (jam, jarak, koordinat).
- `.g-display` untuk judul: bobot 700, tinggi baris 1.05, tracking -0.03em,
  `text-wrap: balance`. Ukuran selalu `clamp()`, tidak pernah ukuran tetap.
- Angka penting memakai `tabular-nums` supaya tidak bergoyang saat berubah.

## Components

`.g-tile` adalah unit dasar: kartu tonal, padding `clamp(1.5rem, 3vw, 2.25rem)`,
radius 28px yang berubah jadi 56px 28px 56px 28px saat hover. Tombol ikon bulat
untuk aksi baris (`TombolIkon`), dialog konfirmasi untuk aksi merusak, bottom
sheet untuk profil guru di HP.

## Motion

Hanya transisi yang membantu memahami perubahan: radius kartu, warna hover,
chevron dropdown. Tidak ada animasi masuk yang menunda pembacaan, tidak ada
parallax. Semua di bawah 300ms dengan `--g-emphasized`.

## Anti-patterns

- Gradien ungu, ilustrasi 3D, badge "Trusted by", mockup laptop mengambang.
- Klaim, angka, testimoni, atau logo instansi yang tidak nyata.
- Gambar besar atau font tambahan di jalur guru: HP lawas adalah acuan.
- Garis tipis sebagai satu-satunya pemisah; pakai perbedaan permukaan.

## Surfaces

### `/` — Welcome (Persuade)

Pintu masuk untuk guru sekolah ini, bukan halaman jualan. Yang harus terjadi
dalam satu layar: guru yakin ini aplikasi yang benar (nama dan logo dari
Pengaturan), tahu tiga syarat absennya berhasil, lalu menekan Masuk.

Urutan: identitas → kalimat pembuka + tombol Masuk → tiga syarat (di lokasi,
sidik jari, satu HP) → cara pakai tiga langkah → catatan untuk admin. Tanpa foto,
tanpa aset eksternal; seluruh visual dari tipografi, nada permukaan, dan bentuk.
