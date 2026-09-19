<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Kesiswaan\ImporSiswa;
use App\Actions\Kesiswaan\TempatkanSiswa;
use App\Enums\JenisKelamin;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImporSiswaRequest;
use App\Http\Requests\Admin\SimpanSiswaRequest;
use App\Http\Requests\Admin\SinkronkanOrangTuaSiswaRequest;
use App\Models\AnggotaKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiswaController extends Controller
{
    /**
     * Daftar siswa, dipaginasi di server.
     *
     * Sekolah ini punya ratusan siswa dan HP guru jadi acuan; mengirim seluruh
     * buku induk ke browser lalu menyaringnya di sana bukan pilihan.
     */
    public function index(Request $request): Response
    {
        $cari = trim((string) $request->string('cari'));
        $kantorId = $request->integer('kantor_id') ?: null;

        return Inertia::render('admin/Siswa', [
            'hasilImpor' => session('impor_siswa'),
            'siswas' => Siswa::query()
                ->with(['kantor:id,nama', 'orangTuas:id,name,email'])
                ->when($cari !== '', fn ($query) => $query->where(
                    fn ($q) => $q->where('nama', 'like', '%'.$cari.'%')->orWhere('nis', 'like', $cari.'%')
                ))
                ->when($kantorId !== null, fn ($query) => $query->where('kantor_id', $kantorId))
                ->orderBy('nama')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Siswa $siswa): array => [
                    'id' => $siswa->id,
                    'kantor_id' => $siswa->kantor_id,
                    'kantor' => $siswa->kantor?->nama,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'nama' => $siswa->nama,
                    'jenis_kelamin' => $siswa->jenis_kelamin->value,
                    'jenis_kelamin_label' => $siswa->jenis_kelamin->label(),
                    'tanggal_lahir' => $siswa->tanggal_lahir?->toDateString(),
                    'is_active' => $siswa->is_active,
                    'orang_tuas' => $siswa->orangTuas->map(fn (User $orangTua): array => [
                        'id' => $orangTua->id,
                        'name' => $orangTua->name,
                        'email' => $orangTua->email,
                    ])->values(),
                ]),
            'kelases' => Kelas::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama', 'kantor_id']),
            'kantors' => Kantor::query()->orderBy('nama')->get(['id', 'nama']),
            'orangTuas' => User::query()
                ->where('role', Role::OrangTua)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'jenisKelamins' => array_map(
                fn (JenisKelamin $jk): array => ['value' => $jk->value, 'label' => $jk->label()],
                JenisKelamin::cases(),
            ),
            'filter' => ['cari' => $cari, 'kantor_id' => $kantorId],
        ]);
    }

    public function store(SimpanSiswaRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $kelas = null;
            if ($request->filled('kelas_id')) {
                $kelas = Kelas::query()->where('kantor_id', $request->integer('kantor_id'))->where('is_active', true)->find($request->integer('kelas_id'));
                if (! $kelas) {
                    throw ValidationException::withMessages(['kelas_id' => 'Kelas tidak tersedia pada unit dan tahun ajaran yang dipilih.']);
                }
            }
            $siswa = Siswa::create($request->safe()->except('kelas_id'));
            if ($kelas) {
                app(TempatkanSiswa::class)($siswa, $kelas, today());
            }
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Siswa ditambahkan.']);

        return to_route('admin.siswa.index');
    }

    public function update(SimpanSiswaRequest $request, Siswa $siswa): RedirectResponse
    {
        if ($siswa->kantor_id !== $request->integer('kantor_id') && AnggotaKelas::query()->where('siswa_id', $siswa->id)->exists()) {
            throw ValidationException::withMessages(['kantor_id' => 'Unit siswa yang memiliki riwayat kelas tidak boleh diubah.']);
        }
        $siswa->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data siswa diperbarui.']);

        return to_route('admin.siswa.index');
    }

    public function sinkronkanOrangTua(SinkronkanOrangTuaSiswaRequest $request, Siswa $siswa): RedirectResponse
    {
        /** @var list<int> $userIds */
        $userIds = $request->validated('user_ids');
        $siswa->orangTuas()->sync($userIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akses orang tua diperbarui.']);

        return to_route('admin.siswa.index');
    }

    /** Template berisi contoh, bukan data pribadi dari buku induk. */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $keluaran = fopen('php://output', 'wb');
            if ($keluaran === false) {
                return;
            }
            fputcsv($keluaran, ImporSiswa::KOLOM, ',', '"', '');
            fputcsv($keluaran, ['Aisyah Putri', '20260001', '0091234567', 'P', '2015-04-11', '5A'], ',', '"', '');
            fclose($keluaran);
        }, 'template-siswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function impor(ImporSiswaRequest $request, ImporSiswa $impor): RedirectResponse
    {
        $berkas = $request->file('berkas');
        abort_unless($berkas instanceof UploadedFile, 422);
        $hasil = $impor((string) $berkas->getRealPath(), $request->integer('kantor_id'));

        return to_route('admin.siswa.index')->with('impor_siswa', $hasil);
    }

    /**
     * Hapus hanya siswa yang belum pernah masuk kelas.
     *
     * Begitu ada riwayat keanggotaan, menghapusnya berarti membuang absensi
     * yang menempel padanya. Siswa yang keluar atau lulus dinonaktifkan.
     */
    public function destroy(Siswa $siswa): RedirectResponse
    {
        $punyaRiwayat = AnggotaKelas::query()->where('siswa_id', $siswa->id)->exists();

        if ($punyaRiwayat) {
            $siswa->update(['is_active' => false]);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Siswa dinonaktifkan. Riwayat kelas dan kehadirannya tetap tersimpan.',
            ]);

            return to_route('admin.siswa.index');
        }

        $siswa->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Siswa dihapus.']);

        return to_route('admin.siswa.index');
    }
}
