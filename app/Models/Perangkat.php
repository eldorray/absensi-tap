<?php

namespace App\Models;

use App\Enums\StatusPerangkat;
use Database\Factories\PerangkatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string $label
 * @property string|null $user_agent
 * @property StatusPerangkat $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'uuid', 'label', 'user_agent', 'status'])]
class Perangkat extends Model
{
    /** @use HasFactory<PerangkatFactory> */
    use HasFactory;

    protected $table = 'perangkats';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPerangkat::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
