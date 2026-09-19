<?php

namespace App\Actions\Kesiswaan;

use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\Kelas;
use App\Models\SesiAbsensiSiswa;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membuka finalisasi yang terlanjur ditekan, kembali ke draft.
 *
 * Hanya berlaku untuk sesi hari ini: salah pencet ketahuan dalam hitungan
 * menit, sedangkan hari lampau sudah mungkin dibaca orang tua -- membukanya
 * adalah urusan admin lewat status Dikoreksi, bukan guru.
 */
class BukaFinalisasiAbsensiSiswa
{
    public function execute(Kelas $kelas, User $guru, Carbon $tanggal): SesiAbsensiSiswa
    {
        if (! $tanggal->isToday()) {
            throw new AuthorizationException('Finalisasi hari lampau hanya dapat dibuka admin.');
        }

        if (! Kelas::query()->diampuOleh($guru, $tanggal)->whereKey($kelas->id)->exists()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($kelas, $guru, $tanggal): SesiAbsensiSiswa {
            $sesi = SesiAbsensiSiswa::query()->withoutGlobalScopes()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->lockForUpdate()->first();

            if ($sesi === null || $sesi->status !== StatusSesiAbsensiSiswa::Final) {
                throw new AuthorizationException('Hanya absensi berstatus final yang dapat dibuka.');
            }

            $sesi->update([
                'status' => StatusSesiAbsensiSiswa::Draft,
                'dibuka_oleh' => $guru->id,
                'dibuka_pada' => now(),
            ]);

            return $sesi->refresh();
        }, 3);
    }
}
