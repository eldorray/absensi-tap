<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\PengumumanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $judul
 * @property string $isi
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['judul', 'isi', 'is_active'])]
class Pengumuman extends Model
{
    use BelongsToTahunAjaran;

    /** @use HasFactory<PengumumanFactory> */
    use HasFactory;

    protected $table = 'pengumumans';

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
