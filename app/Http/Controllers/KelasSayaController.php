<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class KelasSayaController extends Controller
{
    public function index(Request $request): Response
    {
        $tanggal = Carbon::today();

        $kelas = Kelas::query()
            ->diampuOleh($request->user(), $tanggal)
            ->where('is_active', true)
            ->with('kantor:id,nama')
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get()
            ->map(fn (Kelas $kelas): array => [
                'id' => $kelas->id,
                'nama' => $kelas->nama,
                'tingkat' => $kelas->tingkat,
                'kantor' => $kelas->kantor?->nama,
                'anggotas' => AnggotaKelas::query()
                    ->where('kelas_id', $kelas->id)
                    ->berlakuPada($tanggal)
                    ->with('siswa:id,nis,nama')
                    ->get()
                    ->sortBy(fn (AnggotaKelas $anggota): string => (string) $anggota->siswa?->nama)
                    ->values()
                    ->map(fn (AnggotaKelas $anggota): array => [
                        'id' => $anggota->siswa_id,
                        'nis' => $anggota->siswa?->nis,
                        'nama' => $anggota->siswa?->nama,
                    ])
                    ->all(),
            ])
            ->all();

        return Inertia::render('kelas-saya/Index', ['kelas' => $kelas]);
    }
}
