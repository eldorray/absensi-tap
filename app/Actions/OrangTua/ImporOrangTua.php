<?php

namespace App\Actions\OrangTua;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Membuat akun orang tua dari berkas CSV hasil ekspor atau pengisian Excel.
 *
 * Kolom nama wajib. Email dan password dapat dikosongkan; sistem membuatkan
 * keduanya dan mengembalikannya sekali agar admin dapat membagikannya.
 */
class ImporOrangTua
{
    /** @var list<string> */
    public const KOLOM = ['nama', 'email', 'password'];

    public const MAKSIMAL_BARIS = 500;

    /**
     * @return array{dibuat: int, dilewati: int, galat: list<string>, akun: list<array{nama: string, email: string, password: string}>}
     */
    public function __invoke(string $path): array
    {
        $berkas = fopen($path, 'rb');

        if ($berkas === false) {
            return $this->hasil(galat: ['Berkas tidak bisa dibaca.']);
        }

        try {
            return $this->baca($berkas);
        } finally {
            fclose($berkas);
        }
    }

    /**
     * @param  resource  $berkas
     * @return array{dibuat: int, dilewati: int, galat: list<string>, akun: list<array{nama: string, email: string, password: string}>}
     */
    private function baca($berkas): array
    {
        $pemisah = $this->pemisah($berkas);
        $judul = fgetcsv($berkas, 0, $pemisah, '"', '');

        if ($judul === false) {
            return $this->hasil(galat: ['Berkas kosong.']);
        }

        $judul[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $judul[0]);
        $peta = [];

        foreach ($judul as $indeks => $nama) {
            $peta[strtolower(trim((string) $nama))] = $indeks;
        }

        if (! isset($peta['nama'])) {
            return $this->hasil(galat: ['Kolom "nama" tidak ditemukan. Pakai templatenya.']);
        }

        $hasil = $this->hasil();
        $emailTerpakai = [];
        $nomor = 1;

        while (($baris = fgetcsv($berkas, 0, $pemisah, '"', '')) !== false) {
            $nomor++;

            if ($this->kosong($baris)) {
                continue;
            }

            if ($nomor - 1 > self::MAKSIMAL_BARIS) {
                $hasil['galat'][] = 'Berkas melebihi '.self::MAKSIMAL_BARIS.' baris, sisanya tidak diproses.';
                break;
            }

            $nama = $this->sel($baris, $peta, 'nama');
            $emailBerkas = $this->sel($baris, $peta, 'email');
            $passwordBerkas = $this->sel($baris, $peta, 'password');
            $email = $emailBerkas === null
                ? $this->emailBaru($nama ?? '', $emailTerpakai)
                : strtolower((string) $emailBerkas);
            $passwordDibuat = $passwordBerkas === null;
            $password = $passwordDibuat ? $this->passwordBaru() : $passwordBerkas;

            if (isset($emailTerpakai[$email])) {
                $hasil['dilewati']++;
                $hasil['galat'][] = 'Baris '.$nomor.': email '.$email.' muncul dua kali di berkas.';

                continue;
            }

            $validator = Validator::make([
                'name' => $nama,
                'email' => $email,
                'password' => $password,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::default()],
            ]);

            if ($validator->fails()) {
                $hasil['dilewati']++;
                $hasil['galat'][] = 'Baris '.$nomor.': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $data = $validator->validated();

            DB::transaction(function () use ($data): void {
                $orangTua = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);

                $orangTua->forceFill([
                    'role' => Role::OrangTua,
                    'nip' => null,
                    'kantor_id' => null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])->save();
            });

            $emailTerpakai[$email] = true;
            $hasil['dibuat']++;

            if ($passwordDibuat) {
                $hasil['akun'][] = [
                    'nama' => (string) $nama,
                    'email' => $email,
                    'password' => (string) $password,
                ];
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, bool>  $emailTerpakai
     */
    private function emailBaru(string $nama, array $emailTerpakai): string
    {
        $dasar = Str::slug($nama, '.');
        $dasar = $dasar === '' ? 'orang-tua' : $dasar;
        $email = $dasar.'@'.config('absensi.domain_email');
        $urutan = 1;

        while (isset($emailTerpakai[$email]) || User::query()->where('email', $email)->exists()) {
            $urutan++;
            $email = $dasar.$urutan.'@'.config('absensi.domain_email');
        }

        return $email;
    }

    private function passwordBaru(): string
    {
        return Str::password(12, symbols: false);
    }

    /** @param resource $berkas */
    private function pemisah($berkas): string
    {
        $awal = fgets($berkas);
        rewind($berkas);

        if ($awal === false) {
            return ',';
        }

        return substr_count($awal, ';') > substr_count($awal, ',') ? ';' : ',';
    }

    /**
     * @param  list<string|null>  $baris
     * @param  array<string, int>  $peta
     */
    private function sel(array $baris, array $peta, string $kolom): ?string
    {
        $indeks = $peta[$kolom] ?? null;

        if ($indeks === null || ! array_key_exists($indeks, $baris)) {
            return null;
        }

        $nilai = trim((string) $baris[$indeks]);

        return $nilai === '' ? null : $nilai;
    }

    /** @param list<string|null> $baris */
    private function kosong(array $baris): bool
    {
        foreach ($baris as $sel) {
            if (trim((string) $sel) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{dibuat: int, dilewati: int, galat: list<string>, akun: list<array{nama: string, email: string, password: string}>}
     */
    private function hasil(?array $galat = null): array
    {
        return [
            'dibuat' => 0,
            'dilewati' => 0,
            'galat' => $galat ?? [],
            'akun' => [],
        ];
    }
}
