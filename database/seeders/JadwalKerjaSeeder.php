<?php

namespace Database\Seeders;

use App\Models\JadwalKerja;
use App\Models\PengaturanAbsensi;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class JadwalKerjaSeeder extends Seeder
{
    /**
     * Jadwal default sekolah: Senin sampai Sabtu 07:00-14:00, Minggu bukan hari kerja.
     *
     * Baris ini ber-user_id null, jadi berlaku untuk setiap guru yang belum
     * punya jadwal sendiri. Aturan menitnya ikut dibuat di pengaturan_absensis.
     *
     * Dibuat idempotent supaya aman dijalankan ulang di server yang sudah berjalan.
     */
    public function run(): void
    {
        // Jadwal disekat per tahun ajaran, jadi tahunnya harus ada lebih dulu.
        $this->call(TahunAjaranSeeder::class);

        PengaturanAbsensi::current();

        // Tahun ajarannya diisi eksplisit, bukan mengandalkan hook creating:
        // DatabaseSeeder memakai WithoutModelEvents, jadi di sana stempel
        // otomatisnya mati dan barisnya akan lahir tanpa tahun ajaran.
        $tahunAjaranId = TahunAjaran::aktif()?->id;

        foreach (range(0, 6) as $day) {
            // forceFill, bukan firstOrNew($atribut): tahun_ajaran_id sengaja
            // tidak fillable supaya tidak bisa dititipkan lewat request, jadi
            // fill() akan membuangnya diam-diam.
            $jadwal = JadwalKerja::query()
                ->withoutGlobalScopes()
                ->whereNull('user_id')
                ->where('day_of_week', $day)
                ->where('tahun_ajaran_id', $tahunAjaranId)
                ->first() ?? new JadwalKerja;

            $jadwal->forceFill([
                'tahun_ajaran_id' => $tahunAjaranId,
                'user_id' => null,
                'day_of_week' => $day,
                'jam_masuk' => '07:00:00',
                'jam_pulang' => '14:00:00',
                'is_hari_kerja' => $day !== 0,
            ])->save();
        }
    }
}
