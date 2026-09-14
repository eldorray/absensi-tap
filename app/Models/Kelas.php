<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTahunAjaran;
use Carbon\CarbonInterface;
use Database\Factories\KelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kelas/rombel milik satu tahun ajaran.
 *
 * tahun_ajaran_id tidak fillable: nilainya distempel trait dari tahun yang
 * sedang dilihat, dan menerimanya dari request berarti admin bisa membuat
 * kelas di tahun mana pun lewat form yang dipalsukan.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $kantor_id
 * @property string $nama
 * @property int $tingkat
 * @property int|null $wali_kelas_id
 * @property bool $is_active
 */
#[Fillable(['kantor_id', 'nama', 'tingkat', 'wali_kelas_id', 'is_active'])]
class Kelas extends Model
{
    /** @use HasFactory<KelasFactory> */
    use BelongsToTahunAjaran, HasFactory;

    protected $table = 'kelas';

    /**
     * @return BelongsTo<Kantor, $this>
     */
    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    /**
     * @return HasMany<AnggotaKelas, $this>
     */
    public function anggotas(): HasMany
    {
        return $this->hasMany(AnggotaKelas::class);
    }

    /**
     * @return HasMany<GuruKelas, $this>
     */
    public function pengganti(): HasMany
    {
        return $this->hasMany(GuruKelas::class);
    }

    /**
     * Kelas yang boleh diabsen guru ini pada satu tanggal.
     *
     * Dua jalan masuk: wali kelasnya, atau guru pengganti yang penugasannya
     * mencakup tanggal itu. Admin sengaja tidak diistimewakan di sini --
     * akses admin lewat gate 'admin', dan mencampurnya ke scope ini membuat
     * layar guru diam-diam menampilkan seluruh kelas sekolah kepada admin.
     *
     * Batas tanggalnya dibandingkan lewat where() biasa terhadap string
     * ternormalisasi, bukan whereDate(): tanggal_mulai/tanggal_selesai di
     * guru_kelas bertipe DATE lewat cast, dan whereDate() di atas kolom yang
     * sudah DATE membungkus kolomnya sendiri sehingga index pada tabel itu
     * tidak sargable lagi -- pola yang sama seperti
     * AnggotaKelas::scopeBerlakuPada().
     *
     * @param  Builder<Kelas>  $query
     * @return Builder<Kelas>
     */
    public function scopeDiampuOleh(Builder $query, User $guru, ?CarbonInterface $tanggal = null): Builder
    {
        $batas = ($tanggal ?? Carbon::today())->copy()->startOfDay()->toDateTimeString();

        return $query->where(function (Builder $q) use ($guru, $batas): void {
            $q->where('wali_kelas_id', $guru->id)
                ->orWhereHas('pengganti', function (Builder $p) use ($guru, $batas): void {
                    $p->where('user_id', $guru->id)
                        ->where(function (Builder $b) use ($batas): void {
                            $b->whereNull('tanggal_mulai')->orWhere('tanggal_mulai', '<=', $batas);
                        })
                        ->where(function (Builder $b) use ($batas): void {
                            $b->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $batas);
                        });
                });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
