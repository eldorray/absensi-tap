<?php

namespace App\Actions\Kesiswaan;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalan menempatkan atau memindahkan siswa antar-kelas.
 *
 * Aturannya cuma satu tapi keras: pada satu waktu, satu siswa berada di satu
 * kelas. Perpindahan tidak menimpa apa pun -- keanggotaan lama ditutup sehari
 * sebelum yang baru dimulai, jadi tidak ada tanggal yang dimiliki dua kelas
 * dan tidak ada hari yang tidak dimiliki siapa pun.
 *
 * is_active BUKAN sumber kebenaran. Ia kolom turunan yang ditulis ulang di
 * sini setiap kali cakupan tanggal berubah, semata supaya query sederhana
 * ("siapa saja yang aktif sekarang") tidak perlu menghitung cakupan setiap
 * saat. Semua logic cakupan di kelas ini -- baris mana yang ditutup, baris
 * mana yang berlaku -- WAJIB bercabang dari tanggal_mulai/tanggal_selesai,
 * tidak pernah dari is_active. Baris yang sudah ditutup lebih dulu lewat
 * keluarkan() (mis. pengumuman keluar di akhir semester) tetap harus ikut
 * ditutup ulang kalau cakupan tanggalnya masih menyentuh penempatan baru --
 * kalau logic ini bercabang dari is_active, baris seperti itu lolos tak
 * tersentuh dan berlakuPada() mengembalikan dua kelas untuk hari yang sama.
 */
class TempatkanSiswa
{
    /**
     * Tempatkan siswa di kelas terhitung tanggal tertentu.
     *
     * Memanggilnya dua kali dengan kelas dan tanggal yang sama -- selama
     * baris sebelumnya masih terbuka -- mengembalikan baris yang sama: admin
     * yang menekan tombol dua kali tidak menghasilkan dua riwayat. Begitu
     * baris itu sudah ditutup (dipindah atau dikeluarkan), pencarian ini
     * sengaja tidak lagi menganggapnya cocok -- lihat pastikanTidakBackdate().
     *
     * @throws InvalidArgumentException Kalau $mulai jatuh pada atau sesudah
     *                                  tanggal_mulai riwayat yang sudah ada untuk siswa ini -- menulis
     *                                  ulang masa lalu seperti itu bukan kasus yang didukung modul ini.
     */
    public function __invoke(Siswa $siswa, Kelas $kelas, CarbonInterface $mulai): AnggotaKelas
    {
        return DB::transaction(function () use ($siswa, $kelas, $mulai): AnggotaKelas {
            $mulaiUntukQuery = $this->keBatasQuery($mulai);

            $sudahAda = AnggotaKelas::query()
                ->where('siswa_id', $siswa->id)
                ->where('kelas_id', $kelas->id)
                ->where('tanggal_mulai', $mulaiUntukQuery)
                ->whereNull('tanggal_selesai')
                ->lockForUpdate()
                ->first();

            if ($sudahAda !== null) {
                return $sudahAda;
            }

            $this->pastikanTidakBackdate($siswa, $mulai);
            $this->tutupYangMasihBerlaku($siswa, $mulai);

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
     *
     * @throws InvalidArgumentException Kalau $selesai jatuh sebelum
     *                                  tanggal_mulai baris ini -- itu akan menulis interval negatif.
     */
    public function keluarkan(AnggotaKelas $anggota, CarbonInterface $selesai): void
    {
        if ($selesai->lt($anggota->tanggal_mulai)) {
            throw new InvalidArgumentException(sprintf(
                'Tanggal selesai %s tidak boleh sebelum tanggal mulai %s.',
                $selesai->toDateString(),
                $anggota->tanggal_mulai->toDateString(),
            ));
        }

        $anggota->update([
            'tanggal_selesai' => $selesai->toDateString(),
            'is_active' => false,
        ]);
    }

    /**
     * Tolak penempatan yang mundur ke tanggal yang sudah dilewati riwayat.
     *
     * Kalau siswa ini sudah punya baris -- aktif ataupun sudah ditutup --
     * yang tanggal_mulai-nya pada atau setelah $mulai, menutupnya di sini
     * berarti menulis tanggal_selesai yang lebih awal dari tanggal_mulai
     * baris itu sendiri (interval negatif). Itu kesalahan operator (koreksi
     * mundur, atau kelas lain di tanggal yang sama), bukan kasus yang
     * didukung modul ini, jadi ditolak tegas lewat exception daripada diam-
     * diam menghasilkan data yang tidak masuk akal.
     */
    private function pastikanTidakBackdate(Siswa $siswa, CarbonInterface $mulai): void
    {
        $konflik = AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->where('tanggal_mulai', '>=', $this->keBatasQuery($mulai))
            ->orderBy('tanggal_mulai')
            ->lockForUpdate()
            ->first();

        if ($konflik !== null) {
            throw new InvalidArgumentException(sprintf(
                'Tidak bisa menempatkan siswa mulai %s: sudah ada keanggotaan (kelas #%d) yang mulai %s.',
                $mulai->toDateString(),
                $konflik->kelas_id,
                $konflik->tanggal_mulai->toDateString(),
            ));
        }
    }

    /**
     * Tutup keanggotaan yang cakupan tanggalnya masih menyentuh $mulai,
     * sehari sebelum yang baru mulai.
     *
     * Baris yang ditutup dipilih dari cakupan tanggalnya (tanggal_mulai
     * sebelum $mulai, dan tanggal_selesai kosong atau pada/setelah $mulai) --
     * bukan dari flag is_active. Ditutup dengan clamp: tanggal_selesai baru
     * tidak pernah didorong lebih maju dari tanggal tutup yang sudah ada
     * lebih awal (mis. baris yang sudah ditutup lewat keluarkan()).
     */
    private function tutupYangMasihBerlaku(Siswa $siswa, CarbonInterface $mulai): void
    {
        $batasQuery = $this->keBatasQuery($mulai);
        $batasBaru = $mulai->copy()->subDay();

        AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->where('tanggal_mulai', '<', $batasQuery)
            ->where(function (Builder $q) use ($batasQuery): void {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $batasQuery);
            })
            ->lockForUpdate()
            ->get()
            ->each(function (AnggotaKelas $anggota) use ($batasBaru): void {
                $selesaiLama = $anggota->tanggal_selesai;

                $selesaiBaru = ($selesaiLama !== null && $selesaiLama->lt($batasBaru))
                    ? $selesaiLama
                    : $batasBaru;

                $anggota->update([
                    'tanggal_selesai' => $selesaiBaru->toDateString(),
                    'is_active' => false,
                ]);
            });
    }

    /**
     * Normalkan tanggal ke bentuk yang benar-benar tersimpan di kolom.
     *
     * Eloquent menulis kolom date lewat format penuh koneksinya (lihat
     * fromDateTime()), jadi yang tersimpan bisa berupa "2026-07-15 00:00:00",
     * bukan "2026-07-15" polos. Query mentah di kelas ini tidak lewat cast
     * model, jadi nilai pembandingnya harus disamakan ke bentuk itu --
     * kalau tidak, perbandingan tepat di batas tanggal_mulai/tanggal_selesai
     * meleset.
     */
    private function keBatasQuery(CarbonInterface $tanggal): string
    {
        return $tanggal->copy()->startOfDay()->toDateTimeString();
    }
}
