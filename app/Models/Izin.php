<?php

namespace App\Models;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use Database\Factories\IzinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property TipeIzin $tipe
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string $alasan
 * @property string|null $lampiran_path
 * @property StatusIzin $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $catatan_review
 * @property-read User $user
 * @property-read User|null $reviewer
 */
#[Fillable(['user_id', 'tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'lampiran_path'])]
class Izin extends Model
{
    /** @use HasFactory<IzinFactory> */
    use HasFactory;

    protected $table = 'izins';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => TipeIzin::class,
            'status' => StatusIzin::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
