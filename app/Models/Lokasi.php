<?php

namespace App\Models;

use Database\Factories\LokasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $kantor_id
 * @property string $nama
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meter
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kantor_id', 'nama', 'latitude', 'longitude', 'radius_meter', 'is_active'])]
class Lokasi extends Model
{
    /** @use HasFactory<LokasiFactory> */
    use HasFactory;

    protected $table = 'lokasis';

    /**
     * Lokasi absen yang berlaku untuk seorang guru.
     *
     * Guru tanpa kantor diukur ke semua lokasi aktif. Guru yang sudah
     * ditugaskan diukur ke lokasi kantornya, ditambah lokasi tanpa kantor --
     * itu titik milik yayasan yang dipakai bersama semua unit.
     *
     * @param  Builder<Lokasi>  $query
     * @return Builder<Lokasi>
     */
    public function scopeAktifUntukKantor(Builder $query, ?int $kantorId): Builder
    {
        return $query
            ->where('is_active', true)
            ->when($kantorId !== null, fn (Builder $builder) => $builder->where(
                fn (Builder $bagian) => $bagian->whereNull('kantor_id')->orWhere('kantor_id', $kantorId),
            ));
    }

    /**
     * @return BelongsTo<Kantor, $this>
     */
    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
