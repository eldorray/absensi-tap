<?php

namespace App\Models;

use Database\Factories\LokasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meter
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'latitude', 'longitude', 'radius_meter', 'is_active'])]
class Lokasi extends Model
{
    /** @use HasFactory<LokasiFactory> */
    use HasFactory;

    protected $table = 'lokasis';

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
