<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\JadwalKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jam masuk dan pulang satu hari, milik satu guru.
 *
 * Baris dengan user_id null adalah jadwal default sekolah: dipakai setiap guru
 * yang tidak punya baris sendiri di hari itu, jadi admin hanya perlu mengisi
 * jadwal yang menyimpang. Toleransi dan jendela absen tidak ada di sini --
 * keduanya global, lihat PengaturanAbsensi.
 *
 * @property int $id
 * @property int|null $user_id null = jadwal default sekolah
 * @property int $day_of_week 0 = Minggu, sesuai Carbon::dayOfWeek
 * @property string $jam_masuk
 * @property string $jam_pulang
 * @property bool $is_hari_kerja
 */
#[Fillable(['user_id', 'day_of_week', 'jam_masuk', 'jam_pulang', 'is_hari_kerja'])]
class JadwalKerja extends Model
{
    use BelongsToTahunAjaran;

    /** @use HasFactory<JadwalKerjaFactory> */
    use HasFactory;

    protected $table = 'jadwal_kerjas';

    /**
     * Jadwal sepekan yang berlaku untuk satu guru, dikunci day_of_week.
     *
     * Jadwal milik guru menimpa default sekolah pada hari yang sama.
     *
     * @return array<int, self>
     */
    public static function untukGuru(int $userId): array
    {
        $peta = [];

        $baris = static::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
            // Default dulu, jadwal guru menyusul dan menimpanya di peta.
            ->orderByRaw('user_id is null desc')
            ->get();

        foreach ($baris as $jadwal) {
            $peta[$jadwal->day_of_week] = $jadwal;
        }

        return $peta;
    }

    /**
     * Jadwal satu guru pada satu hari, atau null kalau hari itu tidak diatur.
     */
    public static function hariUntukGuru(int $userId, int $dayOfWeek): ?self
    {
        return static::untukGuru($userId)[$dayOfWeek] ?? null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_hari_kerja' => 'boolean',
        ];
    }
}
