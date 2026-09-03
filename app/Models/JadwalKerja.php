<?php

namespace App\Models;

use Database\Factories\JadwalKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $day_of_week 0 = Minggu, sesuai Carbon::dayOfWeek
 * @property string $jam_masuk
 * @property string $jam_pulang
 * @property int $toleransi_menit
 * @property bool $is_hari_kerja
 */
#[Fillable(['day_of_week', 'jam_masuk', 'jam_pulang', 'toleransi_menit', 'is_hari_kerja'])]
class JadwalKerja extends Model
{
    /** @use HasFactory<JadwalKerjaFactory> */
    use HasFactory;

    protected $table = 'jadwal_kerjas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'toleransi_menit' => 'integer',
            'is_hari_kerja' => 'boolean',
        ];
    }
}
