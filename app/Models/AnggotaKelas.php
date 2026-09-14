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
 * @property bool $is_active
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
     * @param  Builder<AnggotaKelas>  $query
     * @return Builder<AnggotaKelas>
     */
    public function scopeBerlakuPada(Builder $query, CarbonInterface $tanggal): Builder
    {
        return $query->whereDate('tanggal_mulai', '<=', $tanggal)
            ->where(function (Builder $q) use ($tanggal): void {
                $q->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $tanggal);
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
