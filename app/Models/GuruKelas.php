<?php

namespace App\Models;

use Database\Factories\GuruKelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Penugasan guru pengganti pada satu kelas.
 *
 * Wali kelas tidak dicatat di sini -- lihat kelas.wali_kelas_id.
 *
 * @property int $id
 * @property int $kelas_id
 * @property int $user_id
 * @property Carbon|null $tanggal_mulai
 * @property Carbon|null $tanggal_selesai
 */
#[Fillable(['kelas_id', 'user_id', 'tanggal_mulai', 'tanggal_selesai'])]
class GuruKelas extends Model
{
    /** @use HasFactory<GuruKelasFactory> */
    use HasFactory;

    protected $table = 'guru_kelas';

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }
}
