<?php

namespace App\Actions\Absensi;

use App\Enums\HasilTap;
use App\Enums\StatusAbsensi;
use App\Enums\StatusPerangkat;
use App\Enums\TipeTap;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\Perangkat;
use App\Models\User;
use App\Support\Jarak;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatatAbsensi
{
    /**
     * Batas ketidakpastian GPS yang masih diterima, dalam meter.
     *
     * Di atas ini koordinat terlalu kabur untuk membuktikan guru ada di dalam
     * radius sekolah. iOS yang hanya diberi izin lokasi kasar akan jatuh di sini,
     * jadi pesan errornya harus menyebut "Lokasi Tepat" secara eksplisit.
     */
    public const AKURASI_MAKSIMAL_METER = 75;

    /**
     * Umur maksimal penanda verifikasi biometrik, dalam detik.
     */
    public const UMUR_VERIFIKASI_DETIK = 120;

    /**
     * Session key penanda verifikasi biometrik yang baru saja lolos.
     */
    public const KEY_VERIFIKASI = 'absensi.passkey_verified_at';

    /**
     * Catat satu tap. Setiap penolakan tetap menulis jejak di absensi_attempts.
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $guru,
        TipeTap $tipe,
        float $latitude,
        float $longitude,
        int $accuracy,
        string $perangkatUuid,
        bool $passkeyTerverifikasi,
    ): Absensi {
        $dasar = [
            'user_id' => $guru->id,
            'tipe' => $tipe,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meter' => $accuracy,
            'perangkat_uuid' => $perangkatUuid,
            'terverifikasi' => false,
        ];

        $perangkat = Perangkat::query()
            ->where('user_id', $guru->id)
            ->where('uuid', $perangkatUuid)
            ->where('status', StatusPerangkat::Active)
            ->first();

        if ($perangkat === null) {
            $this->tolak($dasar, HasilTap::PerangkatAsing, 'HP ini belum terdaftar untuk akunmu. Hubungi TU.');
        }

        if ($accuracy > self::AKURASI_MAKSIMAL_METER) {
            $this->tolak(
                $dasar,
                HasilTap::AkurasiBuruk,
                "Sinyal GPS lemah (±{$accuracy} m). Coba di luar ruangan dan aktifkan Lokasi Tepat.",
            );
        }

        $lokasi = $this->lokasiTerdekat($latitude, $longitude);

        if ($lokasi === null) {
            $this->tolak($dasar, HasilTap::LuarRadius, 'Belum ada lokasi absen aktif. Hubungi TU.');
        }

        $jarak = Jarak::meter($latitude, $longitude, $lokasi->latitude, $lokasi->longitude);

        $dasar = [...$dasar, 'jarak_meter' => $jarak, 'lokasi_id' => $lokasi->id];

        if ($jarak > $lokasi->radius_meter) {
            $this->tolak(
                $dasar,
                HasilTap::LuarRadius,
                "Kamu {$jarak} m dari sekolah. Absen hanya bisa dalam {$lokasi->radius_meter} m.",
            );
        }

        // Guru yang punya passkey tidak boleh melewati biometrik. Kalau boleh,
        // lapisan "membuktikan orang" jadi opsional dan anti-titip bubar.
        if ($guru->hasPasskeysEnabled() && ! $passkeyTerverifikasi) {
            $this->tolak($dasar, HasilTap::PasskeyInvalid, 'Verifikasi sidik jari dulu, lalu tap lagi.');
        }

        $absensi = Absensi::query()->firstOrNew([
            'user_id' => $guru->id,
            // Carbon, bukan toDateString(): cast 'date' pada Absensi::tanggal
            // menormalkan setiap nilai yang di-set jadi 'Y-m-d 00:00:00'.
            // String tanggal-saja tidak akan pernah cocok dengan baris yang
            // sudah tersimpan, jadi WHERE ini tidak boleh dibandingkan mentah.
            'tanggal' => today(),
        ]);

        $kolom = $tipe === TipeTap::Masuk ? 'masuk_attempt_id' : 'pulang_attempt_id';

        if ($absensi->{$kolom} !== null) {
            $this->tolak($dasar, HasilTap::Duplikat, "Sudah absen {$tipe->value} hari ini.");
        }

        if ($tipe === TipeTap::Pulang && $absensi->masuk_attempt_id === null) {
            $this->tolak($dasar, HasilTap::BelumMasuk, 'Belum ada absen masuk hari ini.');
        }

        try {
            return DB::transaction(function () use ($guru, $tipe, $dasar, $passkeyTerverifikasi, $absensi, $kolom): Absensi {
                $attempt = AbsensiAttempt::create([
                    ...$dasar,
                    'terverifikasi' => $passkeyTerverifikasi,
                    'hasil' => HasilTap::Diterima,
                ]);

                $jadwal = JadwalKerja::query()->where('day_of_week', now()->dayOfWeek)->first();

                $absensi->user_id = $guru->id;
                // Carbon::today(), bukan today(): AppServiceProvider memasang
                // Date::use(CarbonImmutable::class), tapi Absensi::$tanggal
                // bertipe Illuminate\Support\Carbon (mutable).
                $absensi->tanggal = Carbon::today();
                $absensi->{$kolom} = $attempt->id;

                if ($tipe === TipeTap::Masuk) {
                    $absensi->status = $this->statusMasuk($jadwal);
                } else {
                    $absensi->pulang_cepat = $this->pulangCepat($jadwal);
                }

                $absensi->save();

                return $absensi;
            });
        } catch (UniqueConstraintViolationException) {
            // Tap serentak: dua request lolos pengecekan duplikat di atas sebelum
            // salah satunya sempat menyimpan, lalu bentrok di constraint unique
            // (user_id, tanggal) saat INSERT. Transaksi sudah di-rollback --
            // termasuk baris AbsensiAttempt "Diterima" yang dibuat di dalamnya --
            // jadi tolak() di sini menulis ulang jejak auditnya di luar transaksi.
            $this->tolak($dasar, HasilTap::Duplikat, "Sudah absen {$tipe->value} hari ini.");
        }
    }

    /**
     * Tulis jejak percobaan yang gagal, lalu batalkan permintaan.
     *
     * @param  array<string, mixed>  $atribut
     *
     * @throws ValidationException
     */
    private function tolak(array $atribut, HasilTap $hasil, string $pesan): never
    {
        AbsensiAttempt::create([...$atribut, 'hasil' => $hasil]);

        throw ValidationException::withMessages(['tap' => $pesan]);
    }

    /**
     * Lokasi aktif terdekat dari koordinat guru.
     *
     * ponytail: seluruh lokasi aktif dimuat ke memori lalu diurut di PHP. Satu
     * sekolah punya segelintir lokasi, jadi ini gratis. Kalau jumlahnya pernah
     * mencapai ratusan, pindahkan ke query dengan bounding box lintang/bujur.
     */
    private function lokasiTerdekat(float $latitude, float $longitude): ?Lokasi
    {
        return Lokasi::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Lokasi $lokasi): int => Jarak::meter(
                $latitude,
                $longitude,
                $lokasi->latitude,
                $lokasi->longitude,
            ))
            ->first();
    }

    private function statusMasuk(?JadwalKerja $jadwal): StatusAbsensi
    {
        if ($jadwal === null) {
            return StatusAbsensi::Hadir;
        }

        $batas = today()
            ->setTimeFromTimeString($jadwal->jam_masuk)
            ->addMinutes($jadwal->toleransi_menit);

        return now()->greaterThan($batas) ? StatusAbsensi::Terlambat : StatusAbsensi::Hadir;
    }

    private function pulangCepat(?JadwalKerja $jadwal): bool
    {
        if ($jadwal === null) {
            return false;
        }

        return now()->lessThan(today()->setTimeFromTimeString($jadwal->jam_pulang));
    }
}
