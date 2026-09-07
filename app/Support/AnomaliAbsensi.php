<?php

namespace App\Support;

use App\Models\Absensi;
use App\Models\AbsensiAttempt;

/**
 * Penanda kejanggalan pada satu baris absensi.
 *
 * Dipakai rekap bulanan dan rekap harian. Aturannya sengaja satu tempat: ini
 * yang dilihat admin untuk mencurigai titip absen, jadi kedua rekap tidak boleh
 * berbeda pendapat.
 */
final class AnomaliAbsensi
{
    /**
     * @param  list<string>  $kembar  kunci 'tanggal|lat|lng' yang dipakai lebih dari satu guru
     * @return list<string>
     */
    public static function untuk(?Absensi $absensi, array $kembar): array
    {
        if ($absensi === null) {
            return [];
        }

        $anomali = [];
        $attempt = $absensi->masukAttempt;

        if ($attempt !== null && ! $attempt->terverifikasi) {
            $anomali[] = 'tanpa_biometrik';
        }

        if ($attempt !== null && in_array(self::kunci($attempt), $kembar, true)) {
            $anomali[] = 'koordinat_kembar';
        }

        if ($absensi->pulang_cepat) {
            $anomali[] = 'pulang_cepat';
        }

        if ($absensi->pulang_attempt_id === null) {
            $anomali[] = 'belum_tap_pulang';
        }

        return $anomali;
    }

    /**
     * Kunci koordinat satu percobaan: tanggal, lintang, dan bujurnya.
     */
    public static function kunci(AbsensiAttempt $attempt): string
    {
        return $attempt->created_at?->toDateString().'|'.$attempt->latitude.'|'.$attempt->longitude;
    }

    /**
     * @return array<string, string>
     */
    public static function label(): array
    {
        return [
            'tanpa_biometrik' => 'Tanpa biometrik',
            'koordinat_kembar' => 'Koordinat kembar',
            'pulang_cepat' => 'Pulang cepat',
            'belum_tap_pulang' => 'Belum tap pulang',
        ];
    }
}
