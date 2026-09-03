<?php

namespace App\Models;

use Database\Factories\HariLiburFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $tanggal
 * @property string $nama
 */
#[Fillable(['tanggal', 'nama'])]
class HariLibur extends Model
{
    /** @use HasFactory<HariLiburFactory> */
    use HasFactory;

    protected $table = 'hari_liburs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
