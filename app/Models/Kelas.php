<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\KelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelas/rombel milik satu tahun ajaran.
 *
 * tahun_ajaran_id tidak fillable: nilainya distempel trait dari tahun yang
 * sedang dilihat, dan menerimanya dari request berarti admin bisa membuat
 * kelas di tahun mana pun lewat form yang dipalsukan.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $kantor_id
 * @property string $nama
 * @property int $tingkat
 * @property int|null $wali_kelas_id
 * @property bool $is_active
 */
#[Fillable(['kantor_id', 'nama', 'tingkat', 'wali_kelas_id', 'is_active'])]
class Kelas extends Model
{
    /** @use HasFactory<KelasFactory> */
    use BelongsToTahunAjaran, HasFactory;

    protected $table = 'kelas';

    /**
     * @return BelongsTo<Kantor, $this>
     */
    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    /**
     * @return HasMany<AnggotaKelas, $this>
     */
    public function anggotas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
