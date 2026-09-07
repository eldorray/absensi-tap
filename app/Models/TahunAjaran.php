<?php

namespace App\Models;

use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tahun ajaran yang menyekat seluruh data absensi.
 *
 * @property int $id
 * @property string $nama
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property bool $is_active
 */
#[Fillable(['nama', 'tanggal_mulai', 'tanggal_selesai'])]
class TahunAjaran extends Model
{
    /** @use HasFactory<TahunAjaranFactory> */
    use HasFactory;

    protected $table = 'tahun_ajarans';

    /**
     * Tahun ajaran yang sedang aktif, atau null kalau belum ada.
     */
    public static function aktif(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Jadikan tahun ini satu-satunya yang aktif.
     *
     * Tidak menghapus apa pun: data tahun sebelumnya tetap tersimpan dengan
     * tahun_ajaran_id-nya sendiri, jadi rekap lama masih bisa dibuka dan
     * diekspor dengan memilih tahunnya.
     */
    public function aktifkan(): void
    {
        DB::transaction(function (): void {
            static::query()->where('is_active', true)->whereKeyNot($this->id)->update(['is_active' => false]);
            $this->forceFill(['is_active' => true])->save();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
