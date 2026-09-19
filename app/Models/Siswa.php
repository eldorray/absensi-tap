<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use Database\Factories\SiswaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Siswa tidak mempunyai akun sendiri. Orang tua yang ditautkan admin masuk
 * dengan akunnya masing-masing dan hanya membaca hasil yang dipublikasikan.
 *
 * Siswa nonaktif tidak dihapus. Kehadirannya tahun lalu tetap harus bisa
 * dibuka dan dicetak setelah dia lulus atau pindah.
 *
 * @property int $id
 * @property int $kantor_id
 * @property string $nis
 * @property string|null $nisn
 * @property string $nama
 * @property JenisKelamin $jenis_kelamin
 * @property Carbon|null $tanggal_lahir
 * @property string|null $foto
 * @property bool $is_active
 */
#[Fillable(['kantor_id', 'nis', 'nisn', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'is_active'])]
class Siswa extends Model
{
    /** @use HasFactory<SiswaFactory> */
    use HasFactory;

    protected $table = 'siswas';

    /**
     * @return BelongsTo<Kantor, $this>
     */
    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    /**
     * @return array<string, string>
     */
    /** @return HasMany<AnggotaKelas, $this> */
    public function keanggotaanKelas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function orangTuas(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'orang_tua_siswa')->withTimestamps();
    }

    /** @return HasMany<AbsensiSiswa, $this> */
    public function absensis(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class);
    }

    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'tanggal_lahir' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
