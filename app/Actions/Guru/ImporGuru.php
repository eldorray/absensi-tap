<?php

namespace App\Actions\Guru;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Buat akun guru dari berkas CSV hasil ekspor Excel.
 *
 * Hanya kolom nama yang wajib. Email dan password boleh dikosongkan dan akan
 * dibuatkan -- daftar guru yang sekolah punya biasanya hanya nama dan NIP, dan
 * memaksa admin mengarang 40 email lebih dulu membuat fitur ini tidak terpakai.
 * Akun yang dibuat dikembalikan beserta passwordnya supaya admin bisa
 * membagikannya; itu satu-satunya kesempatan melihat password tersebut.
 *
 * Baris yang salah dilewati dan dilaporkan per nomor baris, baris yang benar
 * tetap dibuat: admin dengan 40 baris tidak perlu mengulang semuanya hanya
 * karena satu email kembar.
 */
class ImporGuru
{
    /**
     * Kolom yang wajib ada di baris judul, apa pun urutannya.
     */
    public const KOLOM = ['nama', 'nip', 'email', 'password'];

    /**
     * Batas baris satu berkas, supaya satu unggahan tidak menahan request lama.
     */
    public const MAKSIMAL_BARIS = 500;

    /**
     * Domain email yang dipakai saat kolom email dikosongkan.
     *
     * Email ini cuma identitas masuk, bukan alamat surat: akun dibuat admin dan
     * langsung terverifikasi, jadi tidak ada surat yang dikirim ke sana.
     */
    public function domain(): string
    {
        return (string) config('absensi.domain_email');
    }

    /**
     * @return array{dibuat: int, dilewati: int, galat: list<string>, akun: list<array{nama: string, email: string, password: string}>}
     */
    public function __invoke(string $path): array
    {
        $berkas = fopen($path, 'rb');

        if ($berkas === false) {
            return ['dibuat' => 0, 'dilewati' => 0, 'galat' => ['Berkas tidak bisa dibaca.'], 'akun' => []];
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
        $judul = fgetcsv($berkas, 0, $pemisah);

        if ($judul === false) {
            return ['dibuat' => 0, 'dilewati' => 0, 'galat' => ['Berkas kosong.'], 'akun' => []];
        }

        // BOM UTF-8 dari Excel menempel di sel pertama dan merusak nama kolom.
        $judul[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $judul[0]);
        $peta = [];

        foreach ($judul as $indeks => $nama) {
            $peta[strtolower(trim((string) $nama))] = $indeks;
        }

        // Hanya nama yang wajib ada. Kolom lain boleh tidak ditulis sama sekali.
        if (! isset($peta['nama'])) {
            return [
                'dibuat' => 0,
                'dilewati' => 0,
                'galat' => ['Kolom "nama" tidak ditemukan. Pakai templatenya.'],
                'akun' => [],
            ];
        }

        $dibuat = 0;
        $dilewati = 0;
        $galat = [];
        $akun = [];
        $emailTerpakai = [];
        $nomor = 1;

        while (($baris = fgetcsv($berkas, 0, $pemisah)) !== false) {
            $nomor++;

            if ($this->kosong($baris)) {
                continue;
            }

            if ($nomor - 1 > self::MAKSIMAL_BARIS) {
                $galat[] = 'Berkas melebihi '.self::MAKSIMAL_BARIS.' baris, sisanya tidak diproses.';
                break;
            }

            $nama = $this->sel($baris, $peta, 'nama');
            $nip = $this->sel($baris, $peta, 'nip');
            $emailBerkas = $this->sel($baris, $peta, 'email');
            $passwordBerkas = $this->sel($baris, $peta, 'password');

            $data = [
                'name' => $nama,
                'nip' => $nip,
                'email' => $emailBerkas === null
                    ? $this->emailBaru($nama ?? '', $nip, $emailTerpakai)
                    : strtolower($emailBerkas),
                'password' => $passwordBerkas ?? $this->passwordBaru(),
            ];

            if (isset($emailTerpakai[$data['email']])) {
                $dilewati++;
                $galat[] = 'Baris '.$nomor.': email '.$data['email'].' muncul dua kali di berkas.';

                continue;
            }

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'nip' => ['nullable', 'string', 'max:30'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::default()],
            ]);

            if ($validator->fails()) {
                $dilewati++;
                $galat[] = 'Baris '.$nomor.': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $this->buat($validator->validated());
            $emailTerpakai[$data['email']] = true;
            $dibuat++;

            // Password dibalikkan hanya untuk yang dibuatkan sistem; yang
            // ditulis sendiri di berkas sudah dipegang admin.
            if ($passwordBerkas === null) {
                $akun[] = [
                    'nama' => (string) $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ];
            }
        }

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati, 'galat' => $galat, 'akun' => $akun];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buat(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $guru = User::create([
                'name' => $data['name'],
                'nip' => $data['nip'] ?? null,
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $guru->forceFill([
                'role' => Role::Guru,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        });
    }

    /**
     * Email masuk untuk guru yang tidak menuliskannya.
     *
     * NIP dipakai lebih dulu karena pasti unik di satu sekolah; kalau kosong,
     * namanya dijadikan slug dan diberi angka sampai tidak bentrok -- baik
     * dengan akun yang sudah ada maupun dengan baris lain di berkas yang sama.
     *
     * @param  array<string, bool>  $emailTerpakai
     */
    private function emailBaru(string $nama, ?string $nip, array $emailTerpakai): string
    {
        $dasar = $nip !== null && $nip !== ''
            ? preg_replace('/[^a-z0-9]/', '', strtolower($nip))
            : Str::slug($nama, '.');
        $dasar = $dasar === '' || $dasar === null ? 'guru' : $dasar;

        $email = $dasar.'@'.$this->domain();
        $urutan = 1;

        while (isset($emailTerpakai[$email]) || User::query()->where('email', $email)->exists()) {
            $urutan++;
            $email = $dasar.$urutan.'@'.$this->domain();
        }

        return $email;
    }

    /**
     * Password awal yang dibuatkan sistem.
     *
     * Panjang dan acak, bukan turunan nama atau NIP: password yang bisa ditebak
     * dari data yang beredar di sekolah sama saja dengan tanpa password.
     */
    private function passwordBaru(): string
    {
        return Str::password(12, symbols: false);
    }

    /**
     * Tebak pemisah dari baris judul: Excel berbahasa Indonesia menyimpan CSV
     * dengan titik koma, versi Inggris dengan koma.
     *
     * @param  resource  $berkas
     */
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

    /**
     * @param  list<string|null>  $baris
     */
    private function kosong(array $baris): bool
    {
        foreach ($baris as $sel) {
            if (trim((string) $sel) !== '') {
                return false;
            }
        }

        return true;
    }
}
