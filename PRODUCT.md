# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Guru MI dan SMP di bawah satu yayasan, memakai HP pribadi masing-masing di halaman
sekolah, sering dengan sinyal seadanya dan perangkat lawas. Mereka membuka aplikasi
dua kali sehari selama kurang dari satu menit: tap masuk pagi, tap pulang siang.

Admin/TU memakai aplikasi yang sama dari desktop untuk menyetujui HP baru, meninjau
izin, mengatur jadwal, dan mencetak rekap.

## Product Purpose

Mencatat kehadiran guru dengan bukti yang tidak bisa dititipkan. Berhasil kalau
rekap bulanan bisa langsung dipakai kepala sekolah tanpa perlu dikoreksi manual,
dan kalau guru tidak pernah gagal absen karena aplikasinya.

## Positioning

Tiga lapis bukti yang dipasang berbarengan, bukan satu: koordinat di dalam radius
sekolah, biometrik HP (passkey) yang membuktikan orangnya, dan satu HP terikat per
akun yang harus disetujui admin sebelum bisa dipakai. Jejak setiap percobaan tap
disimpan, termasuk yang ditolak, jadi pola titip absen terlihat di rekap.

## Operating Context

- Guru: buka halaman absen, lihat jarak ke titik sekolah, tap masuk atau pulang.
- Jendela absen: masuk dibuka dan ditutup relatif jam masuk, pulang dibuka sebelum
  jam pulang. Di luar jendela, tap ditolak dengan alasan yang menyebut jamnya.
- Admin: dashboard, rekap harian dan bulanan, izin masuk, jadwal guru, pengumuman,
  kantor, kelola user dan role, tahun ajaran, pengaturan.
- Seluruh data absensi disekat per tahun ajaran aktif.

## Capabilities and Constraints

- Geofence per lokasi dengan radius meter; guru yang ditugaskan ke satu kantor
  diukur ke lokasi kantornya ditambah lokasi bersama.
- Akurasi GPS di atas 75 m ditolak.
- Absensi, izin, jadwal, hari libur, dan pengumuman terikat `tahun_ajaran_id`.
- Pendaftaran mandiri dimatikan; akun dibuat admin, manual atau impor CSV.
- Nama, logo, dan favicon aplikasi diatur admin dari layar Pengaturan.
- Istilah yang dipakai konsisten dalam Bahasa Indonesia: tap, absen masuk, absen
  pulang, jendela absen, toleransi, hari efektif, kantor, tahun ajaran.

## Brand Commitments

- Seluruh antarmuka Bahasa Indonesia.
- Identitas (nama dan logo) datang dari pengaturan aplikasi, bukan dari kode.

## Evidence on Hand

Belum ada foto sekolah, logo tetap, testimoni, angka pemakaian, atau daftar
sekolah pemakai. Halaman publik tidak boleh mengarang satu pun dari itu.

## Product Principles

1. Guru berhasil absen dalam sepuluh detik atau aplikasinya gagal.
2. Setiap penolakan menyebut alasannya beserta angka atau jamnya.
3. Bukti kehadiran berlapis; melepas satu lapis membatalkan gunanya.
4. Admin mengatur dari layar, bukan dari file konfigurasi.

## Accessibility & Inclusion

HP lawas dengan sinyal lemah adalah perangkat acuan, bukan kasus tepi. Target
sentuh besar, kontras tinggi di bawah matahari, dan tidak ada alur yang bergantung
pada animasi.
