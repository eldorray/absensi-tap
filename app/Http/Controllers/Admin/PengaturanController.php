<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanHariLiburRequest;
use App\Http\Requests\Admin\SimpanJadwalRequest;
use App\Http\Requests\Admin\SimpanLokasiRequest;
use App\Http\Requests\Admin\SimpanPengaturanAbsensiRequest;
use App\Http\Requests\Admin\SimpanPengaturanAplikasiRequest;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Kantor;
use App\Models\Lokasi;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanAplikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanController extends Controller
{
    public function edit(): Response
    {
        $aplikasi = PengaturanAplikasi::current();

        return Inertia::render('admin/Pengaturan', [
            'lokasis' => Lokasi::query()->with('kantor:id,nama')->orderBy('nama')->get(['id', 'kantor_id', 'nama', 'latitude', 'longitude', 'radius_meter', 'is_active']),
            'kantors' => Kantor::query()->orderBy('nama')->get(['id', 'nama']),
            'jadwals' => JadwalKerja::query()->whereNull('user_id')->orderBy('day_of_week')->get(['day_of_week', 'jam_masuk', 'jam_pulang', 'is_hari_kerja']),
            'pengaturan' => PengaturanAbsensi::current()->only(['toleransi_menit', 'buka_masuk_menit', 'tutup_masuk_menit', 'buka_pulang_menit']),
            'aplikasi' => [
                'nama' => $aplikasi->nama,
                'logo_url' => $aplikasi->logoUrl(),
                'favicon_url' => $aplikasi->faviconUrl(),
            ],
            'hariLiburs' => HariLibur::query()->orderBy('tanggal')->get(['id', 'tanggal', 'nama']),
        ]);
    }

    /**
     * Simpan nama, logo, dan favicon aplikasi.
     *
     * Berkas lama dihapus setiap kali diganti supaya storage tidak menumpuk
     * logo yang tak dipakai lagi.
     */
    public function simpanAplikasi(SimpanPengaturanAplikasiRequest $request): RedirectResponse
    {
        $aplikasi = PengaturanAplikasi::current();
        $aplikasi->nama = $request->string('nama')->toString();

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $berkas => $kolom) {
            if (! $request->hasFile($berkas)) {
                continue;
            }

            $tersimpan = $request->file($berkas)->store('aplikasi', 'public');

            if ($tersimpan === false) {
                continue;
            }

            $lama = $aplikasi->{$kolom};
            $aplikasi->{$kolom} = $tersimpan;

            if ($lama !== null) {
                Storage::disk('public')->delete($lama);
            }
        }

        $aplikasi->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identitas aplikasi disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function simpanLokasi(SimpanLokasiRequest $request): RedirectResponse
    {
        Lokasi::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    public function ubahLokasi(SimpanLokasiRequest $request, Lokasi $lokasi): RedirectResponse
    {
        $lokasi->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi diperbarui.']);

        return to_route('admin.pengaturan.edit');
    }

    /**
     * Hapus satu lokasi.
     *
     * Lokasi terakhir yang masih aktif ditahan: tanpa lokasi aktif tidak ada
     * guru yang bisa tap, dan kegagalannya baru ketahuan saat guru di gerbang.
     * Jejak absensi_attempts ikut kehilangan lokasi_id (nullOnDelete), tapi
     * koordinat dan jaraknya tetap tersimpan di barisnya masing-masing.
     */
    public function hapusLokasi(Lokasi $lokasi): RedirectResponse
    {
        $tersisa = Lokasi::query()->where('is_active', true)->whereKeyNot($lokasi->id)->exists();

        if ($lokasi->is_active && ! $tersisa) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Lokasi aktif terakhir tidak bisa dihapus.']);

            return to_route('admin.pengaturan.edit');
        }

        $lokasi->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lokasi dihapus.']);

        return to_route('admin.pengaturan.edit');
    }

    public function simpanPengaturanAbsensi(SimpanPengaturanAbsensiRequest $request): RedirectResponse
    {
        PengaturanAbsensi::current()->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aturan jam absen disimpan.']);

        return to_route('admin.pengaturan.edit');
    }

    /**
     * Simpan jadwal default sekolah (baris ber-user_id null).
     */
    public function simpanJadwal(SimpanJadwalRequest $request): RedirectResponse
    {
        /** @var array<int, array<string, mixed>> $jadwals */
        $jadwals = $request->validated()['jadwals'];
        foreach ($jadwals as $jadwal) {
            JadwalKerja::updateOrCreate(
                ['user_id' => null, 'day_of_week' => $jadwal['day_of_week']],
                [
                    'jam_masuk' => $jadwal['jam_masuk'].':00',
                    'jam_pulang' => $jadwal['jam_pulang'].':00',
                    'is_hari_kerja' => $jadwal['is_hari_kerja'],
                ],
            );
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal default disimpan.']);

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
