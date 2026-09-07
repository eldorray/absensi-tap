<?php

namespace App\Models\Scopes;

use App\Support\TahunAjaranTerpilih;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Saring baris ke tahun ajaran yang sedang dilihat.
 *
 * @implements Scope<Model>
 *
 * Kalau belum ada tahun ajaran sama sekali (instalasi baru, sebelum seeder
 * jalan), saringannya dilepas: lebih baik apa adanya daripada seluruh aplikasi
 * terlihat kosong tanpa penjelasan.
 */
class TahunAjaranScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tahunAjaranId = app(TahunAjaranTerpilih::class)->id();

        if ($tahunAjaranId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('tahun_ajaran_id'), $tahunAjaranId);
    }
}
