<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanTahunAjaranRequest;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\Pengumuman;
use App\Models\TahunAjaran;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TahunAjaranController extends Controller
{
    public function index(): Response
    {
        $dilihat = app(TahunAjaranTerpilih::class)->id();

        return Inertia::render('admin/TahunAjaran', [
            'dilihat' => $dilihat,
            'tahunAjarans' => TahunAjaran::query()
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (TahunAjaran $tahun): array => [
                    'id' => $tahun->id,
                    'nama' => $tahun->nama,
                    'tanggal_mulai' => $tahun->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $tahun->tanggal_selesai->toDateString(),
                    'is_active' => $tahun->is_active,
                    'jumlah' => $this->jumlahData($tahun),
                ])
                ->all(),
        ]);
    }

    public function store(SimpanTahunAjaranRequest $request): RedirectResponse
    {
        TahunAjaran::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tahun ajaran ditambahkan.']);

        return to_route('admin.tahun-ajaran.index');
    }

    public function update(SimpanTahunAjaranRequest $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $tahunAjaran->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tahun ajaran diperbarui.']);

        return to_route('admin.tahun-ajaran.index');
    }

    /**
     * Jadikan tahun ini yang aktif untuk seluruh aplikasi.
     *
     * Tidak ada yang dihapus: absensi, izin, jadwal, hari libur, dan pengumuman
     * tahun sebelumnya tetap tersimpan dengan tahun ajarannya sendiri. Yang
     * berubah hanya tahun yang disaring, jadi guru mulai dari layar bersih dan
     * rekap lama masih bisa dibuka lewat tombol "Lihat".
     */
    public function aktifkan(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        $tahunAjaran->aktifkan();
        app(TahunAjaranTerpilih::class)->lupakan();

        // Lepas penengokan tahun lain supaya admin ikut melihat tahun baru.
        $request->session()->forget('tahun_ajaran_id');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tahun ajaran '.$tahunAjaran->nama.' sekarang aktif. Data tahun sebelumnya tetap tersimpan.',
        ]);

        return to_route('admin.tahun-ajaran.index');
    }

    /**
     * Tengok data tahun ajaran lain tanpa mengubah yang aktif.
     */
    public function lihat(Request $request, TahunAjaran $tahunAjaran): RedirectResponse
    {
        if ($tahunAjaran->is_active) {
            $request->session()->forget('tahun_ajaran_id');
        } else {
            $request->session()->put('tahun_ajaran_id', $tahunAjaran->id);
        }

        app(TahunAjaranTerpilih::class)->lupakan();

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Menampilkan data tahun ajaran '.$tahunAjaran->nama.'.']);

        return to_route('admin.tahun-ajaran.index');
    }

    /**
     * Banyaknya data yang terikat ke satu tahun ajaran.
     *
     * @return array<string, int>
     */
    private function jumlahData(TahunAjaran $tahun): array
    {
        return [
            'absensi' => Absensi::query()->withoutGlobalScopes()->where('tahun_ajaran_id', $tahun->id)->count(),
            'izin' => Izin::query()->withoutGlobalScopes()->where('tahun_ajaran_id', $tahun->id)->count(),
            'jadwal' => JadwalKerja::query()->withoutGlobalScopes()->where('tahun_ajaran_id', $tahun->id)->count(),
            'hari_libur' => HariLibur::query()->withoutGlobalScopes()->where('tahun_ajaran_id', $tahun->id)->count(),
            'pengumuman' => Pengumuman::query()->withoutGlobalScopes()->where('tahun_ajaran_id', $tahun->id)->count(),
        ];
    }
}
