<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnggotaKelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Penempatan seorang siswa di satu kelas, berjangka waktu.
 *
 * @property int $id
 * @property int $kelas_id
 * @property int $siswa_id
 * @property Carbon $tanggal_mulai
 * @property Carbon|null $tanggal_selesai
 * @property bool $is_active Kolom turunan, ditulis ulang oleh
 *                           App\Actions\Kesiswaan\TempatkanSiswa setiap kali cakupan tanggal
 *                           berubah. Jangan pernah menulis query cakupan yang bercabang dari
 *                           kolom ini -- pakai tanggal_mulai/tanggal_selesai, seperti
 *                           scopeBerlakuPada() di bawah.
 */
#[Fillable(['kelas_id', 'siswa_id', 'tanggal_mulai', 'tanggal_selesai', 'is_active'])]
class AnggotaKelas extends Model
{
    /** @use HasFactory<AnggotaKelasFactory> */
    use HasFactory;

    protected $table = 'anggota_kelas';

    /**
     * Keanggotaan yang berlaku pada satu tanggal.
     *
     * Dipakai absensi: daftar anggota sebuah sesi diambil dari tanggal sesi
     * itu, bukan dari daftar kelas hari ini. Kalau tidak, mengoreksi absensi
     * bulan lalu akan memakai susunan kelas yang sudah berubah.
     *
     * Sengaja where() biasa dengan tanggal sebagai string, bukan whereDate():
     * tanggal_mulai/tanggal_selesai sudah kolom DATE, dan whereDate() di atas
     * kolom yang sudah DATE membungkus kolomnya sendiri sehingga index
     * (tanggal_mulai, tanggal_selesai) di migration tidak bisa dipakai --
     * padahal scope inilah query yang paling sering jalan (tiap sesi absen).
     *
     * Nilai pembandingnya diformat awal hari ("Y-m-d H:i:s"), bukan cuma
     * "Y-m-d": Eloquent selalu menulis kolom bertipe date lewat format
     * penuh koneksinya (lihat fromDateTime()), jadi yang tersimpan bisa
     * berupa "2026-07-15 00:00:00", bukan "2026-07-15" polos. Query mentah
     * di sini tidak lewat cast model, jadi harus dicocokkan ke bentuk yang
     * benar-benar tersimpan itu, atau perbandingan tanggal di batasnya
     * sendiri (tepat tanggal_mulai/tanggal_selesai) meleset.
     *
     * @param  Builder<AnggotaKelas>  $query
     * @return Builder<AnggotaKelas>
     */
    public function scopeBerlakuPada(Builder $query, CarbonInterface $tanggal): Builder
    {
        $batas = $tanggal->copy()->startOfDay()->toDateTimeString();

        return $query->where('tanggal_mulai', '<=', $batas)
            ->where(function (Builder $q) use ($batas): void {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $batas);
            });
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
