<?php

namespace App\Actions\Kesiswaan;

use App\Enums\JenisKelamin;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Buat data siswa dari berkas CSV hasil ekspor Excel buku induk.
 *
 * Berbeda dengan impor guru: di sini tidak ada akun, tidak ada email, dan
 * tidak ada password. Siswa adalah entitas akademik saja.
 *
 * Kolom wajib hanya nama dan nis. Baris yang salah dilewati dan dilaporkan
 * per nomor baris; baris yang benar tetap dibuat, supaya admin dengan 300
 * baris tidak perlu mengulang semuanya karena satu NIS kembar.
 */
class ImporSiswa
{
    /**
     * Kolom yang dikenali di baris judul, apa pun urutannya.
     */
    public const KOLOM = ['nama', 'nis', 'nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'kelas'];

    /**
     * Batas baris satu berkas, supaya satu unggahan tidak menahan request lama.
     */
    public const MAKSIMAL_BARIS = 1000;

    /**
     * @return array{dibuat: int, dilewati: int, galat: list<string>}
     */
    public function __invoke(string $path, int $kantorId): array
    {
        $berkas = fopen($path, 'rb');

        if ($berkas === false) {
            return ['dibuat' => 0, 'dilewati' => 0, 'galat' => ['Berkas tidak bisa dibaca.']];
        }

        try {
            $this->periksaKelas($berkas, $kantorId);
            rewind($berkas);

            return DB::transaction(fn (): array => $this->baca($berkas, $kantorId));
        } finally {
            fclose($berkas);
        }
    }

    /**
     * @param  resource  $berkas
     * @return array{dibuat: int, dilewati: int, galat: list<string>}
     */
    private function baca($berkas, int $kantorId): array
    {
        $pemisah = $this->pemisah($berkas);
        $judul = fgetcsv($berkas, 0, $pemisah, '"', '');

        if ($judul === false) {
            return ['dibuat' => 0, 'dilewati' => 0, 'galat' => ['Berkas kosong.']];
        }

        // BOM UTF-8 dari Excel menempel di sel pertama dan merusak nama kolom.
        $judul[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $judul[0]);
        $peta = [];

        foreach ($judul as $indeks => $nama) {
            $peta[strtolower(trim((string) $nama))] = $indeks;
        }

        if (! isset($peta['nama']) || ! isset($peta['nis'])) {
            return [
                'dibuat' => 0,
                'dilewati' => 0,
                'galat' => ['Kolom "nama" dan "nis" wajib ada di baris judul. Pakai templatenya.'],
            ];
        }

        $dibuat = 0;
        $dilewati = 0;
        $galat = [];
        $nisTerpakai = [];
        $nomor = 1;

        while (($baris = fgetcsv($berkas, 0, $pemisah, '"', '')) !== false) {
            $nomor++;

            if ($this->kosong($baris)) {
                continue;
            }

            if ($nomor - 1 > self::MAKSIMAL_BARIS) {
                $galat[] = 'Berkas melebihi '.self::MAKSIMAL_BARIS.' baris, sisanya tidak diproses.';
                break;
            }

            $nis = $this->sel($baris, $peta, 'nis');

            if ($nis !== null && isset($nisTerpakai[$nis])) {
                $dilewati++;
                $galat[] = 'Baris '.$nomor.': NIS '.$nis.' muncul dua kali di berkas.';

                continue;
            }

            $data = [
                'kantor_id' => $kantorId,
                'nama' => $this->sel($baris, $peta, 'nama'),
                'nis' => $nis,
                'nisn' => $this->sel($baris, $peta, 'nisn'),
                'jenis_kelamin' => strtoupper((string) ($this->sel($baris, $peta, 'jenis_kelamin') ?? 'L')),
                'tempat_lahir' => $this->sel($baris, $peta, 'tempat_lahir'),
                'tanggal_lahir' => $this->sel($baris, $peta, 'tanggal_lahir'),
                'is_active' => true,
            ];

            $validator = Validator::make($data, [
                'kantor_id' => ['required', 'integer', 'exists:kantors,id'],
                'nama' => ['required', 'string', 'max:120'],
                'nis' => [
                    'required', 'string', 'max:30',
                    Rule::unique('siswas', 'nis')->where(fn ($query) => $query->where('kantor_id', $kantorId)),
                ],
                'nisn' => ['nullable', 'string', 'max:20', 'unique:siswas,nisn'],
                'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],
                'tempat_lahir' => ['nullable', 'string', 'max:120'],
                'tanggal_lahir' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            ]);

            if ($validator->fails()) {
                $dilewati++;
                $galat[] = 'Baris '.$nomor.': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $siswa = Siswa::create($validator->validated() + ['is_active' => true]);
            $namaKelas = $this->sel($baris, $peta, 'kelas');
            $kelas = Kelas::query()->where('kantor_id', $kantorId)->where('is_active', true)->get()->first(fn (Kelas $kelas): bool => $kelas->nama === $namaKelas);
            if (! $kelas) {
                throw ValidationException::withMessages(['berkas' => 'Ada nama kelas yang salah. Impor dibatalkan.']);
            }
            app(TempatkanSiswa::class)($siswa, $kelas, today());
            $nisTerpakai[(string) $nis] = true;
            $dibuat++;
        }

        return ['dibuat' => $dibuat, 'dilewati' => $dilewati, 'galat' => $galat];
    }

    /**
     * Tebak pemisah dari baris judul: Excel berbahasa Indonesia menyimpan CSV
     * dengan titik koma, versi Inggris dengan koma.
     *
     * @param  resource  $berkas
     */
    private function periksaKelas($berkas, int $kantorId): void
    {
        $pemisah = $this->pemisah($berkas);
        $judul = fgetcsv($berkas, 0, $pemisah, '"', '');
        if ($judul === false) {
            throw ValidationException::withMessages(['berkas' => 'Berkas kosong.']);
        }
        $judul[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $judul[0]);
        $kolom = array_search('kelas', array_map(fn ($nilai): string => strtolower(trim((string) $nilai)), $judul), true);
        if ($kolom === false) {
            throw ValidationException::withMessages(['berkas' => 'Kolom kelas wajib ada. Gunakan template CSV terbaru.']);
        }
        $namaKelas = Kelas::query()->where('kantor_id', $kantorId)->where('is_active', true)->pluck('nama')->all();
        $nomor = 1;
        $galat = [];
        while (($baris = fgetcsv($berkas, 0, $pemisah, '"', '')) !== false) {
            $nomor++;
            if ($this->kosong($baris)) {
                continue;
            }
            $nama = trim((string) ($baris[$kolom] ?? ''));
            if (! in_array($nama, $namaKelas, true)) {
                $galat[] = "Baris {$nomor}: nama kelas \"{$nama}\" salah atau tidak tersedia pada unit dan tahun ajaran ini.";
            }
        }
        if ($galat !== []) {
            throw ValidationException::withMessages(['berkas' => array_merge(['Ada nama kelas yang salah. Seluruh impor dibatalkan.'], $galat)]);
        }
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
