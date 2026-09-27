<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTahunAjaran;
use App\Support\IsiKaya;
use Database\Factories\PengumumanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $judul
 * @property string $isi HTML yang sudah dibersihkan IsiKaya
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
     * Setiap jalur tulis (form, seeder, factory) lewat pembersih yang sama,
     * jadi isi yang tersimpan selalu aman dirender sebagai HTML.
     *
     * @return Attribute<string, string>
     */
    protected function isi(): Attribute
    {
        return Attribute::make(set: fn (string $nilai): string => IsiKaya::bersihkan($nilai));
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
