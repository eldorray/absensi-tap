<?php

namespace App\Models;

use Database\Factories\KantorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit sekolah, mis. MI atau SMP.
 *
 * @property int $id
 * @property string $nama
 * @property string|null $jenjang
 * @property string|null $alamat
 * @property bool $is_active
 */
#[Fillable(['nama', 'jenjang', 'alamat', 'is_active'])]
class Kantor extends Model
{
    /** @use HasFactory<KantorFactory> */
    use HasFactory;

    protected $table = 'kantors';

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Lokasi, $this>
     */
    public function lokasis(): HasMany
    {
        return $this->hasMany(Lokasi::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
