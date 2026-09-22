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
 * menit, sedangkan hari lampau sudah mungkin dibaca orang tua. Yang membuka
 * adalah guru piket yang sama dengan yang mengisi -- bukan guru, yang kini
 * hanya membaca.
 */
class BukaFinalisasiAbsensiSiswa
{
    public function execute(Kelas $kelas, User $petugas, Carbon $tanggal): SesiAbsensiSiswa
    {
        if (! $petugas->can('piket')) {
            throw new AuthorizationException('Hanya guru piket yang dapat membuka finalisasi absensi siswa.');
        }

        if (! $tanggal->isToday()) {
            throw new AuthorizationException('Finalisasi hanya dapat dibuka pada hari yang sama.');
        }

        return DB::transaction(function () use ($kelas, $petugas, $tanggal): SesiAbsensiSiswa {
            $sesi = SesiAbsensiSiswa::query()->withoutGlobalScopes()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->lockForUpdate()->first();

            if ($sesi === null || $sesi->status !== StatusSesiAbsensiSiswa::Final) {
                throw new AuthorizationException('Hanya absensi berstatus final yang dapat dibuka.');
            }

            $sesi->update([
                'status' => StatusSesiAbsensiSiswa::Draft,
                'dibuka_oleh' => $petugas->id,
                'dibuka_pada' => now(),
            ]);

            return $sesi->refresh();
        }, 3);
    }
}
