<?php

namespace App\Actions\Kesiswaan;

use App\Enums\StatusKehadiranSiswa;
use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\SesiAbsensiSiswa;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimpanAbsensiSiswa
{
    /**
     * @param  array{catatan?: string|null, absensis: list<array{siswa_id: int, status: string, catatan?: string|null, jam_datang?: string|null}>}  $input
     */
    public function execute(Kelas $kelas, User $guru, Carbon $tanggal, array $input, bool $final): SesiAbsensiSiswa
    {
        if (! Kelas::query()->diampuOleh($guru, $tanggal)->whereKey($kelas->id)->exists()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($kelas, $guru, $tanggal, $input, $final): SesiAbsensiSiswa {
            $sesi = SesiAbsensiSiswa::query()->withoutGlobalScopes()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->lockForUpdate()->first();
            if ($sesi?->status === StatusSesiAbsensiSiswa::Final || $sesi?->status === StatusSesiAbsensiSiswa::Dikoreksi) {
                if ($final) {
                    return $sesi;
                }
                throw new AuthorizationException('Absensi final tidak dapat diubah guru.');
            }
            $sesi ??= SesiAbsensiSiswa::create(['kelas_id' => $kelas->id, 'tanggal' => $tanggal, 'status' => StatusSesiAbsensiSiswa::Draft, 'dibuat_oleh' => $guru->id]);
            $sesi->update(['status' => $final ? StatusSesiAbsensiSiswa::Final : StatusSesiAbsensiSiswa::Draft, 'catatan' => $input['catatan'] ?? null, 'finalisasi_oleh' => $final ? $guru->id : null, 'finalisasi_pada' => $final ? now() : null]);
            $changes = collect($input['absensis'])->keyBy('siswa_id');
            $members = AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->pluck('siswa_id');
            if ($changes->keys()->diff($members)->isNotEmpty()) {
                throw ValidationException::withMessages(['absensis' => 'Siswa bukan anggota kelas pada tanggal sesi.']);
            }
            foreach ($members as $siswaId) {
                $item = $changes->get($siswaId, []);
                $sesi->absensis()->updateOrCreate(['siswa_id' => $siswaId], ['status' => $item['status'] ?? StatusKehadiranSiswa::Hadir->value, 'catatan' => $item['catatan'] ?? null, 'jam_datang' => ($item['status'] ?? null) === 'terlambat' ? ($item['jam_datang'] ?? null) : null, 'dicatat_oleh' => $guru->id]);
            }

            return $sesi->refresh();
        }, 3);
    }
}
