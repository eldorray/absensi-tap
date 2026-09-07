<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan waktu absen yang berlaku untuk seluruh sekolah.
 *
 * @property int $toleransi_menit menit setelah jam masuk sebelum dihitung terlambat
 * @property int $buka_masuk_menit menit sebelum jam masuk saat absen masuk dibuka
 * @property int $tutup_masuk_menit menit sesudah jam masuk saat absen masuk ditutup
 * @property int $buka_pulang_menit menit sebelum jam pulang saat absen pulang dibuka
 */
#[Fillable(['toleransi_menit', 'buka_masuk_menit', 'tutup_masuk_menit', 'buka_pulang_menit'])]
class PengaturanAbsensi extends Model
{
    protected $table = 'pengaturan_absensis';

    /**
     * Baris tunggalnya, dibuat dengan nilai default kalau belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'toleransi_menit' => 'integer',
            'buka_masuk_menit' => 'integer',
            'tutup_masuk_menit' => 'integer',
            'buka_pulang_menit' => 'integer',
        ];
    }
}
