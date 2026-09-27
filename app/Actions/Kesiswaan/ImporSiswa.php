<?php

namespace App\Actions\Kesiswaan;

use App\Enums\JenisKelamin;
use App\Enums\Role;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Sinkronkan data siswa dari berkas CSV hasil ekspor Excel buku induk.
 *
 * Berbeda dengan impor guru: di sini tidak ada akun, tidak ada email, dan
 * tidak ada password. Siswa adalah entitas akademik saja.
 *
 * Siswa dicocokkan lewat NIS per unit: yang sudah ada diperbarui dan
 * ditempatkan ke kelas di berkas, yang belum ada dibuat. Jadi awal tahun ajaran
 * cukup mengunggah buku induk terbaru -- tidak perlu menghapus lalu input
 * ulang, yang akan memutus riwayat kehadiran tahun lalu.
 *
 * Kolom wajib hanya nama dan nis. Baris yang salah dilewati dan dilaporkan
 * per nomor baris; baris yang benar tetap diproses, supaya admin dengan 300
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
     * @param  bool  $nonaktifkanYangHilang  nonaktifkan siswa aktif unit ini
     *                                       yang NIS-nya tidak ada di berkas (lulus/pindah)
     * @return array{dibuat: int, diperbarui: int, dilewati: int, galat: list<string>, dinonaktifkan: list<string>, orang_tua_dinonaktifkan: int}
     */
    public function __invoke(string $path, int $kantorId, bool $nonaktifkanYangHilang = false): array
    {
        $berkas = fopen($path, 'rb');

        if ($berkas === false) {
            return $this->hasil(galat: ['Berkas tidak bisa dibaca.']);
        }

        try {
            $this->periksaKelas($berkas, $kantorId);
            rewind($berkas);

            return DB::transaction(fn (): array => $this->baca($berkas, $kantorId, $nonaktifkanYangHilang));
        } finally {
            fclose($berkas);
        }
    }

    /**
     * @param  resource  $berkas
     * @return array{dibuat: int, diperbarui: int, dilewati: int, galat: list<string>, dinonaktifkan: list<string>, orang_tua_dinonaktifkan: int}
     */
    private function baca($berkas, int $kantorId, bool $nonaktifkanYangHilang): array
    {
        $pemisah = $this->pemisah($berkas);
        $judul = fgetcsv($berkas, 0, $pemisah, '"', '');

        if ($judul === false) {
            return $this->hasil(galat: ['Berkas kosong.']);
        }

        // BOM UTF-8 dari Excel menempel di sel pertama dan merusak nama kolom.
        $judul[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $judul[0]);
        $peta = [];

        foreach ($judul as $indeks => $nama) {
            $peta[strtolower(trim((string) $nama))] = $indeks;
        }

        if (! isset($peta['nama']) || ! isset($peta['nis'])) {
            return $this->hasil(galat: ['Kolom "nama" dan "nis" wajib ada di baris judul. Pakai templatenya.']);
        }

        $hasil = $this->hasil();
        $nisTerpakai = [];
        // Setiap NIS yang tertulis di berkas, termasuk baris yang gagal: siswa
        // yang barisnya salah ketik tidak boleh ikut dinonaktifkan.
        $nisDiBerkas = [];
        $terpotong = false;
        $nomor = 1;
        $kelasAktif = Kelas::query()->where('kantor_id', $kantorId)->where('is_active', true)->get()->keyBy('nama');

        while (($baris = fgetcsv($berkas, 0, $pemisah, '"', '')) !== false) {
            $nomor++;

            if ($this->kosong($baris)) {
                continue;
            }

            if ($nomor - 1 > self::MAKSIMAL_BARIS) {
                $hasil['galat'][] = 'Berkas melebihi '.self::MAKSIMAL_BARIS.' baris, sisanya tidak diproses.';
                $terpotong = true;
                break;
            }

            $nis = $this->sel($baris, $peta, 'nis');

            if ($nis !== null) {
                $nisDiBerkas[$nis] = true;
            }

            if ($nis !== null && isset($nisTerpakai[$nis])) {
                $hasil['dilewati']++;
                $hasil['galat'][] = 'Baris '.$nomor.': NIS '.$nis.' muncul dua kali di berkas.';

                continue;
            }

            $ada = $nis === null
                ? null
                : Siswa::query()->where('kantor_id', $kantorId)->where('nis', $nis)->first();
            $jenisKelamin = $this->sel($baris, $peta, 'jenis_kelamin');

            $data = [
                'kantor_id' => $kantorId,
                'nama' => $this->sel($baris, $peta, 'nama'),
                'nis' => $nis,
                'nisn' => $this->sel($baris, $peta, 'nisn'),
                // Kosong: siswa lama mempertahankan datanya, siswa baru L.
                'jenis_kelamin' => $jenisKelamin === null
                    ? ($ada?->jenis_kelamin->value ?? 'L')
                    : strtoupper($jenisKelamin),
                'tempat_lahir' => $this->sel($baris, $peta, 'tempat_lahir'),
                'tanggal_lahir' => $this->sel($baris, $peta, 'tanggal_lahir'),
            ];

            $validator = Validator::make($data, [
                'kantor_id' => ['required', 'integer', 'exists:kantors,id'],
                'nama' => ['required', 'string', 'max:120'],
                'nis' => [
                    'required', 'string', 'max:30',
                    Rule::unique('siswas', 'nis')->where(fn ($query) => $query->where('kantor_id', $kantorId))->ignore($ada?->id),
                ],
                'nisn' => ['nullable', 'string', 'max:20', Rule::unique('siswas', 'nisn')->ignore($ada?->id)],
                'jenis_kelamin' => ['required', Rule::enum(JenisKelamin::class)],
                'tempat_lahir' => ['nullable', 'string', 'max:120'],
                'tanggal_lahir' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            ]);

            if ($validator->fails()) {
                $hasil['dilewati']++;
                $hasil['galat'][] = 'Baris '.$nomor.': '.implode(' ', $validator->errors()->all());

                continue;
            }

            /** @var array<string, mixed> $valid */
            $valid = $validator->validated();

            if ($ada !== null) {
                // Sel kosong tidak menghapus isian lama di buku induk.
                $ada->update(array_filter($valid, fn (mixed $nilai): bool => $nilai !== null) + ['is_active' => true]);
                $siswa = $ada;
                $hasil['diperbarui']++;
            } else {
                $siswa = Siswa::create($valid + ['is_active' => true]);
                $hasil['dibuat']++;
            }

            $kelas = $kelasAktif->get((string) $this->sel($baris, $peta, 'kelas'));

            if ($kelas === null) {
                throw ValidationException::withMessages(['berkas' => 'Ada nama kelas yang salah. Impor dibatalkan.']);
            }

            try {
                app(TempatkanSiswa::class)($siswa, $kelas, today());
            } catch (InvalidArgumentException $e) {
                $hasil['galat'][] = 'Baris '.$nomor.': data diperbarui, tetapi kelas tidak dipindah. '.$e->getMessage();
            }

            $nisTerpakai[$nis] = true;
        }

        if ($nonaktifkanYangHilang) {
            if ($terpotong || $nisDiBerkas === []) {
                $hasil['galat'][] = 'Tidak ada siswa yang dinonaktifkan: berkas kosong atau terpotong.';
            } else {
                [$hasil['dinonaktifkan'], $hasil['orang_tua_dinonaktifkan']] = $this->nonaktifkanYangHilang(
                    $kantorId,
                    array_map(strval(...), array_keys($nisDiBerkas)),
                );
            }
        }

        return $hasil;
    }

    /**
     * Nonaktifkan siswa aktif yang tidak ada di berkas, lalu akun orang tua
     * yang tidak lagi punya anak aktif. Tidak ada yang dihapus: riwayat kelas,
     * kehadiran, dan izinnya tetap bisa dibuka.
     *
     * @param  list<string>  $nisDiBerkas
     * @return array{0: list<string>, 1: int} siswa dinonaktifkan (NIS – nama), jumlah orang tua dinonaktifkan
     */
    private function nonaktifkanYangHilang(int $kantorId, array $nisDiBerkas): array
    {
        $hilang = Siswa::query()
            ->where('kantor_id', $kantorId)
            ->where('is_active', true)
            ->whereNotIn('nis', $nisDiBerkas)
            ->orderBy('nama')
            ->get(['id', 'nis', 'nama']);

        if ($hilang->isEmpty()) {
            return [[], 0];
        }

        Siswa::query()->whereKey($hilang->modelKeys())->update(['is_active' => false]);

        $orangTua = User::query()
            ->where('role', Role::OrangTua)
            ->where('is_active', true)
            ->whereHas('siswas', fn ($query) => $query->whereKey($hilang->modelKeys()))
            ->whereDoesntHave('siswas', fn ($query) => $query->where('is_active', true))
            ->update(['is_active' => false]);

        return [array_values($hilang->map(fn (Siswa $siswa): string => $siswa->nis.' – '.$siswa->nama)->all()), $orangTua];
    }

    /**
     * @param  list<string>  $galat
     * @return array{dibuat: int, diperbarui: int, dilewati: int, galat: list<string>, dinonaktifkan: list<string>, orang_tua_dinonaktifkan: int}
     */
    private function hasil(array $galat = []): array
    {
        return [
            'dibuat' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'galat' => $galat,
            'dinonaktifkan' => [],
            'orang_tua_dinonaktifkan' => 0,
        ];
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
