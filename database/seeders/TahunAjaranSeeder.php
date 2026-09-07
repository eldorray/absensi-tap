<?php

namespace Database\Seeders;

use App\Models\TahunAjaran;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Database\Seeder;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Tahun ajaran berjalan, aktif.
     *
     * Tahun ajaran dianggap mulai Juli: bulan Januari sampai Juni masih milik
     * tahun ajaran yang dibuka tahun sebelumnya. Idempotent supaya aman
     * dijalankan ulang.
     */
    public function run(): void
    {
        // Dilupakan lebih dulu: kalau binding sudah pernah diselesaikan saat
        // tabelnya masih kosong, nilai null-nya akan menempel di baris yang
        // dibuat seeder lain sesudah ini.
        app(TahunAjaranTerpilih::class)->lupakan();

        if (TahunAjaran::query()->where('is_active', true)->exists()) {
            return;
        }

        $tahun = now()->month >= 7 ? now()->year : now()->year - 1;

        TahunAjaran::query()->updateOrCreate(
            ['nama' => $tahun.'/'.($tahun + 1)],
            [
                'tanggal_mulai' => $tahun.'-07-01',
                'tanggal_selesai' => ($tahun + 1).'-06-30',
                'is_active' => true,
            ],
        );

        app(TahunAjaranTerpilih::class)->lupakan();
    }
}
