<?php

namespace App\Models;

use App\Enums\HasilTap;
use App\Enums\TipeTap;
use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\AbsensiAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property TipeTap $tipe
 * @property float $latitude
 * @property float $longitude
 * @property int $accuracy_meter
 * @property int|null $jarak_meter
 * @property int|null $lokasi_id
 * @property string $perangkat_uuid
 * @property bool $terverifikasi
 * @property HasilTap $hasil
 * @property Carbon|null $created_at
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'tipe', 'latitude', 'longitude', 'accuracy_meter',
    'jarak_meter', 'lokasi_id', 'perangkat_uuid', 'terverifikasi', 'hasil',
])]
class AbsensiAttempt extends Model
{
    use BelongsToTahunAjaran;

    /** @use HasFactory<AbsensiAttemptFactory> */
    use HasFactory;

    protected $table = 'absensi_attempts';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => TipeTap::class,
            'hasil' => HasilTap::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meter' => 'integer',
            'jarak_meter' => 'integer',
            'terverifikasi' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Lokasi yang jaraknya diukur saat tap ini. Null kalau lokasinya sudah
     * dihapus (nullOnDelete) -- koordinat dan jaraknya tetap tersimpan.
     *
     * @return BelongsTo<Lokasi, $this>
     */
    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class);
    }
}
