<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Identitas aplikasi yang dipakai seluruh halaman.
 *
 * @property string $nama
 * @property string|null $logo_path
 * @property string|null $favicon_path
 */
#[Fillable(['nama', 'logo_path', 'favicon_path'])]
class PengaturanAplikasi extends Model
{
    protected $table = 'pengaturan_aplikasis';

    /**
     * Nilai bawaan model, bukan hanya default kolom: baris yang baru dibuat
     * lewat firstOrCreate tidak membaca ulang default dari database, jadi
     * namanya akan null sampai baris itu di-refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'nama' => 'Absensi Guru',
    ];

    /**
     * Baris tunggalnya, dibuat dengan nilai default kalau belum ada.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['nama' => 'Absensi Guru']);
    }

    /**
     * URL logo, atau null kalau belum diunggah.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }

    /**
     * URL favicon, atau null kalau belum diunggah.
     */
    public function faviconUrl(): ?string
    {
        return $this->favicon_path === null ? null : Storage::disk('public')->url($this->favicon_path);
    }
}
