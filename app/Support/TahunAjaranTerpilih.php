<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\TahunAjaran;

/**
 * Tahun ajaran yang sedang dipakai request ini.
 *
 * Dipakai global scope dan stempel baris baru, jadi diselesaikan sekali lalu
 * diingat. Nilai null TIDAK diingat: saat instalasi baru atau migrate:fresh,
 * binding bisa terlanjur diselesaikan sebelum seeder membuat tahunnya, dan
 * kalau null ikut di-cache seluruh baris yang dibuat sesudahnya kehilangan
 * tahun ajaran -- lalu hilang dari setiap rekap.
 */
class TahunAjaranTerpilih
{
    private ?int $id = null;

    /**
     * Admin boleh menengok tahun lain lewat session; guru selalu memakai yang
     * aktif. Tahun yang dipilih tapi sudah dihapus jatuh kembali ke yang aktif.
     */
    public function id(): ?int
    {
        if ($this->id !== null) {
            return $this->id;
        }

        $dipilih = auth()->user()?->role === Role::Admin
            ? session('tahun_ajaran_id')
            : null;

        if ($dipilih !== null) {
            $ada = TahunAjaran::query()->whereKey($dipilih)->value('id');

            if ($ada !== null) {
                return $this->id = (int) $ada;
            }
        }

        $aktif = TahunAjaran::query()->where('is_active', true)->value('id');

        return $this->id = $aktif === null ? null : (int) $aktif;
    }

    /**
     * Lupakan yang sudah diingat. Dipakai seeder sesudah membuat atau
     * mengganti tahun ajaran di proses yang sama.
     */
    public function lupakan(): void
    {
        $this->id = null;
    }
}
