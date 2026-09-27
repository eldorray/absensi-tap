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
     *
     * Jadwal kerja tahun yang sebelumnya aktif disalin kalau tahun ini belum
     * punya jadwal. Tanpa jadwal, tap tidak pernah terlambat dan hari Minggu
     * terhitung alfa di rekap.
     */
    public function aktifkan(): void
    {
        DB::transaction(function (): void {
            $sebelumnya = static::query()->where('is_active', true)->whereKeyNot($this->id)->value('id');

            static::query()->where('is_active', true)->whereKeyNot($this->id)->update(['is_active' => false]);
            $this->forceFill(['is_active' => true])->save();

            if ($sebelumnya !== null) {
                $this->salinJadwalDari((int) $sebelumnya);
            }
        });
    }

    /**
     * Salin jadwal default sekolah dan jadwal khusus guru dari tahun lain.
     *
     * Query builder, bukan model: JadwalKerja disaring global scope tahun
     * ajaran yang sedang dipilih, sedangkan di sini dua tahun disentuh sekaligus.
     */
    private function salinJadwalDari(int $tahunAjaranId): void
    {
        if (DB::table('jadwal_kerjas')->where('tahun_ajaran_id', $this->id)->exists()) {
            return;
        }

        $sekarang = now();

        $baris = DB::table('jadwal_kerjas')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->get(['user_id', 'day_of_week', 'jam_masuk', 'jam_pulang', 'is_hari_kerja'])
            ->map(fn (object $jadwal): array => [
                ...(array) $jadwal,
                'tahun_ajaran_id' => $this->id,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])
            ->all();

        DB::table('jadwal_kerjas')->insert($baris);
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
