<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TahunAjaranScope;
use App\Models\TahunAjaran;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ikat model ini ke tahun ajaran yang sedang dilihat.
 *
 * Dipasang di setiap tabel yang isinya milik satu tahun ajaran. Baris baru
 * distempel otomatis, dan setiap query disaring lewat global scope -- bukan
 * lewat where() yang harus diingat di tiap controller.
 */
trait BelongsToTahunAjaran
{
    protected static function bootBelongsToTahunAjaran(): void
    {
        static::addGlobalScope(new TahunAjaranScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tahun_ajaran_id') === null) {
                $model->setAttribute('tahun_ajaran_id', app(TahunAjaranTerpilih::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
