<?php

namespace App\Actions\Kesiswaan;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan menempatkan atau memindahkan siswa antar-kelas.
 *
 * Aturannya cuma satu tapi keras: pada satu waktu, satu siswa berada di satu
 * kelas. Perpindahan tidak menimpa apa pun -- keanggotaan lama ditutup sehari
 * sebelum yang baru dimulai, jadi tidak ada tanggal yang dimiliki dua kelas
 * dan tidak ada hari yang tidak dimiliki siapa pun.
 */
class TempatkanSiswa
{
    /**
     * Tempatkan siswa di kelas terhitung tanggal tertentu.
     *
     * Memanggilnya dua kali dengan kelas dan tanggal yang sama mengembalikan
     * baris yang sama: admin yang menekan tombol dua kali tidak menghasilkan
     * dua riwayat.
     */
    public function __invoke(Siswa $siswa, Kelas $kelas, CarbonInterface $mulai): AnggotaKelas
    {
        return DB::transaction(function () use ($siswa, $kelas, $mulai): AnggotaKelas {
            $sudahAda = AnggotaKelas::query()
                ->where('siswa_id', $siswa->id)
                ->where('kelas_id', $kelas->id)
                ->whereDate('tanggal_mulai', $mulai)
                ->first();

            if ($sudahAda !== null) {
                return $sudahAda;
            }

            $this->tutupYangMasihAktif($siswa, $mulai);

            return AnggotaKelas::create([
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => null,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Keluarkan siswa dari kelasnya tanpa memindahkannya ke mana pun.
     *
     * Dipakai untuk siswa yang pindah sekolah atau lulus di tengah tahun.
     */
    public function keluarkan(AnggotaKelas $anggota, CarbonInterface $selesai): void
    {
        $anggota->update([
            'tanggal_selesai' => $selesai->toDateString(),
            'is_active' => false,
        ]);
    }

    /**
     * Tutup keanggotaan yang masih terbuka sehari sebelum yang baru mulai.
     */
    private function tutupYangMasihAktif(Siswa $siswa, CarbonInterface $mulai): void
    {
        AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->where('is_active', true)
            ->get()
            ->each(function (AnggotaKelas $anggota) use ($mulai): void {
                $anggota->update([
                    'tanggal_selesai' => $mulai->copy()->subDay()->toDateString(),
                    'is_active' => false,
                ]);
            });
    }
}
