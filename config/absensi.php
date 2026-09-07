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
];
