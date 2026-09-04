<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanHariLiburRequest;
use App\Http\Requests\Admin\SimpanJadwalRequest;
use App\Http\Requests\Admin\SimpanLokasiRequest;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Pengaturan', [
            'lokasis' => Lokasi::query()->orderBy('nama')->get(['id', 'nama', 'latitude', 'longitude', 'radius_meter', 'is_active']),
            'jadwals' => JadwalKerja::query()->orderBy('day_of_week')->get(['day_of_week', 'jam_masuk', 'jam_pulang', 'toleransi_menit', 'is_hari_kerja']),
            'hariLiburs' => HariLibur::query()->orderBy('tanggal')->get(['id', 'tanggal', 'nama']),
        ]);
    }

    public function simpanLokasi(SimpanLokasiRequest $request): RedirectResponse
    {
        Lokasi::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function simpanJadwal(SimpanJadwalRequest $request): RedirectResponse
    {
        /** @var array<int, array<string, mixed>> $jadwals */
        $jadwals = $request->validated()['jadwals'];
        foreach ($jadwals as $jadwal) {
            JadwalKerja::updateOrCreate(['day_of_week' => $jadwal['day_of_week']], [
                'jam_masuk' => $jadwal['jam_masuk'].':00', 'jam_pulang' => $jadwal['jam_pulang'].':00',
                'toleransi_menit' => $jadwal['toleransi_menit'], 'is_hari_kerja' => $jadwal['is_hari_kerja'],
            ]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal kerja disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function simpanHariLibur(SimpanHariLiburRequest $request): RedirectResponse
    {
        HariLibur::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hari libur ditambahkan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function hapusHariLibur(HariLibur $hariLibur): RedirectResponse
    {
        $hariLibur->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hari libur dihapus.']);

        return to_route('admin.pengaturan.edit');
    }
}
