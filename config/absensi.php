<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain email akun guru
    |--------------------------------------------------------------------------
    |
    | Dipakai saat impor guru tidak menyertakan kolom email: alamatnya dibuat
    | dari NIP atau nama, mis. 198501012010012001@sekolah.local. Email ini hanya
    | identitas masuk -- akun dibuat admin dan langsung terverifikasi, jadi
    | tidak ada surat yang dikirim ke sana.
    |
    */

    'domain_email' => env('ABSENSI_DOMAIN_EMAIL', 'sekolah.local'),

    /*
    |--------------------------------------------------------------------------
    | Fitur percobaan
    |--------------------------------------------------------------------------
    |
    | Fitur yang masih dicoba, masing-masing bernama percobaanNNN dan mati
    | kecuali dinyalakan di .env. Kalau hasilnya disukai, jadikan fitur tetap
    | dan buang flag-nya; kalau tidak, hapus kodenya.
    |
    | percobaan001: letupan konfeti di dashboard guru setiap absen berhasil.
    |
    */

    'percobaan' => [
        'percobaan001' => (bool) env('PERCOBAAN001', false),
    ],
];
