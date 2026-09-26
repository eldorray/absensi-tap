<?php

namespace App\Models;

use App\Enums\StatusIzin;
use App\Enums\TipeIzin;
use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\IzinOrangTuaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $siswa_id
 * @property string $nama_pengaju
 * @property string $email_pengaju
 * @property TipeIzin $tipe
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string $alasan
 * @property string|null $lampiran_path
 * @property StatusIzin $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $catatan_review
 * @property-read User|null $user
 * @property-read Siswa $siswa
 * @property-read User|null $reviewer
 */
#[Fillable(['user_id', 'siswa_id', 'nama_pengaju', 'email_pengaju', 'tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'lampiran_path'])]
class IzinOrangTua extends Model
{
    use BelongsToTahunAjaran;

    /** @use HasFactory<IzinOrangTuaFactory> */
    use HasFactory;

    protected $table = 'izin_orang_tuas';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return array<string, string> */
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
}
