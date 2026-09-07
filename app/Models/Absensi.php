<?php

namespace App\Models;

use App\Enums\StatusAbsensi;
use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\AbsensiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $tanggal
 * @property int|null $masuk_attempt_id
 * @property int|null $pulang_attempt_id
 * @property StatusAbsensi|null $status
 * @property bool $pulang_cepat
 * @property-read User $user
 * @property-read AbsensiAttempt|null $masukAttempt
 * @property-read AbsensiAttempt|null $pulangAttempt
 */
#[Fillable(['user_id', 'tanggal', 'masuk_attempt_id', 'pulang_attempt_id', 'status', 'pulang_cepat'])]
class Absensi extends Model
{
    use BelongsToTahunAjaran;

    /** @use HasFactory<AbsensiFactory> */
    use HasFactory;

    protected $table = 'absensis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusAbsensi::class,
            'pulang_cepat' => 'boolean',
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
     * @return BelongsTo<AbsensiAttempt, $this>
     */
    public function masukAttempt(): BelongsTo
    {
        return $this->belongsTo(AbsensiAttempt::class, 'masuk_attempt_id');
    }

    /**
     * @return BelongsTo<AbsensiAttempt, $this>
     */
    public function pulangAttempt(): BelongsTo
    {
        return $this->belongsTo(AbsensiAttempt::class, 'pulang_attempt_id');
    }
}
