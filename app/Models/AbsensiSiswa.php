<?php

namespace App\Models;

use App\Enums\StatusKehadiranSiswa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sesi_absensi_siswa_id
 * @property int $siswa_id
 * @property StatusKehadiranSiswa $status
 * @property string|null $jam_datang
 * @property string|null $catatan
 * @property int $dicatat_oleh
 */
#[Fillable(['sesi_absensi_siswa_id', 'siswa_id', 'status', 'jam_datang', 'catatan', 'dicatat_oleh'])]
class AbsensiSiswa extends Model
{
    /** @return BelongsTo<SesiAbsensiSiswa, $this> */
    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SesiAbsensiSiswa::class, 'sesi_absensi_siswa_id');
    }

    /** @return BelongsTo<Siswa, $this> */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    protected function casts(): array
    {
        return ['status' => StatusKehadiranSiswa::class];
    }
}
