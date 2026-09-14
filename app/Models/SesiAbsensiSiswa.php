<?php

namespace App\Models;

use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\Concerns\BelongsToTahunAjaran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kelas_id
 * @property Carbon $tanggal
 * @property StatusSesiAbsensiSiswa $status
 * @property int|null $dibuat_oleh
 * @property int|null $finalisasi_oleh
 * @property Carbon|null $finalisasi_pada
 * @property string|null $catatan
 * @property-read Collection<int, AbsensiSiswa> $absensis
 */
#[Fillable(['kelas_id', 'tanggal', 'status', 'dibuat_oleh', 'finalisasi_oleh', 'finalisasi_pada', 'catatan'])]
class SesiAbsensiSiswa extends Model
{
    use BelongsToTahunAjaran;

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /** @return HasMany<AbsensiSiswa, $this> */
    public function absensis(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class);
    }

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'finalisasi_pada' => 'datetime', 'status' => StatusSesiAbsensiSiswa::class];
    }
}
