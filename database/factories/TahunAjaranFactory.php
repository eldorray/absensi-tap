<?php

namespace Database\Factories;

use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAjaran>
 */
class TahunAjaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tahun = $this->tahunBelumTerpakai();

        return [
            'nama' => $tahun.'/'.($tahun + 1),
            'tanggal_mulai' => $tahun.'-07-01',
            'tanggal_selesai' => ($tahun + 1).'-06-30',
            'is_active' => false,
        ];
    }

    /**
     * Tahun paling awal yang namanya belum dipakai baris lain.
     *
     * Bukan angka acak: nama tahun ajaran itu unik di tabelnya, dan tahun
     * berjalan sudah lebih dulu dibuat TahunAjaranSeeder. Undian acak dari
     * rentang 41 tahun menabrak baris seeder itu kira-kira sekali per tiga
     * puluh kali jalan, dan yang gagal bukan test yang sedang ditulis
     * melainkan test lain yang kebetulan memakai factory ini -- kegagalan
     * yang mahal dilacak justru karena jarang.
     */
    private function tahunBelumTerpakai(): int
    {
        $terpakai = TahunAjaran::query()->pluck('nama')->all();

        for ($tahun = 2020; $tahun <= 2100; $tahun++) {
            if (! in_array($tahun.'/'.($tahun + 1), $terpakai, true)) {
                return $tahun;
            }
        }

        return 2101;
    }

    public function aktif(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }
}
