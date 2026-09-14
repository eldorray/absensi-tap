<?php

namespace App\Http\Controllers;

use App\Actions\Kesiswaan\SimpanAbsensiSiswa;
use App\Enums\StatusKehadiranSiswa;
use App\Enums\StatusSesiAbsensiSiswa;
use App\Http\Requests\SimpanAbsensiSiswaRequest;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\SesiAbsensiSiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AbsensiSiswaController extends Controller
{
    public function index(Request $request): Response
    {
        $tanggal = Carbon::today();
        $kelas = Kelas::query()->diampuOleh($request->user(), $tanggal)->where('is_active', true)->with('kantor:id,nama')->get()->map(function (Kelas $kelas) use ($tanggal): array {
            $sesi = SesiAbsensiSiswa::query()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->first();

            return ['id' => $kelas->id, 'nama' => $kelas->nama, 'kantor' => $kelas->kantor?->nama, 'jumlah_siswa' => AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->count(), 'status' => $sesi?->status->value ?? StatusSesiAbsensiSiswa::BelumDiperiksa->value, 'terakhir_disimpan' => $sesi?->updated_at?->toIso8601String()];
        })->values()->all();

        return Inertia::render('absensi-siswa/Index', ['kelas' => $kelas, 'tanggal' => $tanggal->toDateString()]);
    }

    public function show(Request $request, Kelas $kelas): Response
    {
        $tanggal = Carbon::today();
        abort_unless(Kelas::query()->diampuOleh($request->user(), $tanggal)->whereKey($kelas->id)->exists(), 403);
        $sesi = SesiAbsensiSiswa::query()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->with('absensis')->first();
        $details = $sesi?->absensis->keyBy('siswa_id') ?? collect();
        $siswas = AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->with('siswa:id,nis,nama')->get()->sortBy(fn (AnggotaKelas $a) => $a->siswa?->nama)->values()->map(function (AnggotaKelas $a) use ($details): array {
            $detail = $details->get($a->siswa_id);

            return ['id' => $a->siswa_id, 'nis' => $a->siswa?->nis, 'nama' => $a->siswa?->nama, 'status' => $detail?->status->value ?? StatusKehadiranSiswa::Hadir->value, 'catatan' => $detail?->catatan, 'jam_datang' => $detail?->jam_datang];
        })->all();
        $status = $sesi === null ? StatusSesiAbsensiSiswa::BelumDiperiksa : $sesi->status;
        $kelas->load('kantor:id,nama', 'tahunAjaran:id,nama');

        return Inertia::render('absensi-siswa/Show', ['kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama, 'kantor' => $kelas->kantor?->nama, 'tahun_ajaran' => $kelas->tahunAjaran?->nama], 'tanggal' => $tanggal->toDateString(), 'siswa' => $siswas, 'sesi' => ['status' => $status->value, 'catatan' => $sesi?->catatan, 'read_only' => in_array($status, [StatusSesiAbsensiSiswa::Final, StatusSesiAbsensiSiswa::Dikoreksi], true)]]);
    }

    public function draft(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $action): RedirectResponse
    {
        return $this->save($request, $kelas, $action, false);
    }

    public function finalisasi(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $action): RedirectResponse
    {
        return $this->save($request, $kelas, $action, true);
    }

    private function save(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $action, bool $final): RedirectResponse
    {
        /** @var array{tanggal: string, catatan?: string|null, absensis: list<array{siswa_id: int, status: string, catatan?: string|null, jam_datang?: string|null}>} $data */
        $data = $request->validated();
        $action->execute($kelas, $request->user(), Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay(), $data, $final);

        return to_route('absensi-siswa.show', $kelas)->with('success', $final ? 'Absensi berhasil difinalisasi.' : 'Draft berhasil disimpan.');
    }
}
