<?php

namespace App\Actions\Absensi;

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DaftarkanPerangkat
{
    /**
     * Ikat satu HP ke seorang guru.
     *
     * HP pertama langsung aktif supaya rollout tidak macet di meja TU. HP kedua
     * dan seterusnya menunggu approve admin -- jalur ganti HP itulah vektor
     * titip absen, jadi hanya itu yang dijaga.
     *
     * @throws ValidationException
     */
    public function __invoke(User $guru, string $uuid, ?string $userAgent): Perangkat
    {
        $terdaftarUntukOrangLain = Perangkat::query()
            ->where('uuid', $uuid)
            ->where('user_id', '!=', $guru->id)
            ->exists();

        if ($terdaftarUntukOrangLain) {
            throw ValidationException::withMessages([
                'device_uuid' => 'HP ini sudah terdaftar untuk guru lain. Hubungi TU.',
            ]);
        }

        $milikSendiri = Perangkat::query()
            ->where('uuid', $uuid)
            ->where('user_id', $guru->id)
            ->first();

        if ($milikSendiri !== null) {
            return $milikSendiri;
        }

        $sudahAdaYangAktif = Perangkat::query()
            ->where('user_id', $guru->id)
            ->where('status', StatusPerangkat::Active)
            ->exists();

        $perangkat = Perangkat::create([
            'user_id' => $guru->id,
            'uuid' => $uuid,
            'label' => self::label($userAgent),
            'user_agent' => $userAgent,
            'status' => $sudahAdaYangAktif ? StatusPerangkat::Pending : StatusPerangkat::Active,
        ]);

        if ($perangkat->status === StatusPerangkat::Active) {
            $perangkat->forceFill(['approved_at' => now()])->save();
        }

        return $perangkat;
    }

    /**
     * Nama perangkat yang cukup untuk dikenali admin di daftar.
     */
    public static function label(?string $userAgent): string
    {
        $userAgent ??= '';

        return match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            default => 'Perangkat lain',
        };
    }
}
