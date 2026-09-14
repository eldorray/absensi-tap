<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Kesiswaan\TempatkanSiswa;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanKelasRequest;
use App\Http\Requests\Admin\TempatkanSiswaRequest;
use App\Models\AnggotaKelas;
use App\Models\GuruKelas;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class KelasController extends Controller
{
    public function index(Request $request): Response
    {
        $terpilih = $request->integer('kelas') ?: null;

        return Inertia::render('admin/Kelas', [
            'hariIni' => Carbon::today()->toDateString(),
            'kelas' => Kelas::query()
                ->with(['kantor:id,nama', 'waliKelas:id,name'])
                ->withCount(['anggotas as jumlah_anggota' => fn ($q) => $q->berlakuPada(Carbon::today())])
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
                ->map(fn (Kelas $k): array => [
                    'id' => $k->id,
                    'kantor_id' => $k->kantor_id,
                    'kantor' => $k->kantor?->nama,
                    'nama' => $k->nama,
                    'tingkat' => $k->tingkat,
                    'wali_kelas_id' => $k->wali_kelas_id,
                    'wali_kelas' => $k->waliKelas?->name,
                    'is_active' => $k->is_active,
                    'jumlah_anggota' => (int) $k->getAttribute('jumlah_anggota'),
                ])
                ->all(),
            'terpilih' => $terpilih === null ? null : $this->detail($terpilih),
            'kantors' => Kantor::query()->orderBy('nama')->get(['id', 'nama']),
            'gurus' => User::query()
                ->whereIn('role', [Role::Guru, Role::Admin])
                ->orderBy('name')
                ->get(['id', 'name', 'nip']),
        ]);
    }

    public function store(SimpanKelasRequest $request): RedirectResponse
    {
        Kelas::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kelas ditambahkan.']);

        return to_route('admin.kelas.index');
    }

    public function update(SimpanKelasRequest $request, Kelas $kelas): RedirectResponse
    {
        if ($kelas->kantor_id !== $request->integer('kantor_id') && $kelas->anggotas()->exists()) {
            throw ValidationException::withMessages(['kantor_id' => 'Unit kelas yang memiliki riwayat anggota tidak boleh diubah.']);
        }
        $kelas->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kelas diperbarui.']);

        return to_route('admin.kelas.index');
    }

    /**
     * Kelas yang pernah diisi siswa tidak dihapus, cukup dinonaktifkan:
     * riwayat keanggotaan dan absensi menempel padanya.
     */
    public function destroy(Kelas $kelas): RedirectResponse
    {
        if (AnggotaKelas::query()->where('kelas_id', $kelas->id)->exists()) {
            $kelas->update(['is_active' => false]);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Kelas dinonaktifkan. Riwayat anggotanya tetap tersimpan.',
            ]);

            return to_route('admin.kelas.index');
        }

        $kelas->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kelas dihapus.']);

        return to_route('admin.kelas.index');
    }

    public function tempatkan(TempatkanSiswaRequest $request, Kelas $kelas, TempatkanSiswa $tempatkan): RedirectResponse
    {
        if (! $kelas->is_active) {
            throw ValidationException::withMessages(['siswa_ids' => 'Aktifkan kelas sebelum menambah anggota.']);
        }
        try {
            $jumlah = DB::transaction(function () use ($request, $kelas, $tempatkan): int {
                $siswas = Siswa::query()->whereIn('id', $request->validated('siswa_ids'))->get();
                $tanggal = Carbon::parse($request->string('tanggal_mulai')->toString());

                foreach ($siswas as $siswa) {
                    $tempatkan($siswa, $kelas, $tanggal);
                }

                return $siswas->count();
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['tanggal_mulai' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $jumlah.' siswa masuk kelas '.$kelas->nama.'.']);

        return to_route('admin.kelas.index', ['kelas' => $kelas->id]);
    }

    public function keluarkan(Kelas $kelas, AnggotaKelas $anggota, TempatkanSiswa $tempatkan): RedirectResponse
    {
        abort_unless($anggota->kelas_id === $kelas->id, 404);

        if ($anggota->tanggal_selesai !== null) {
            throw ValidationException::withMessages(['anggota' => 'Keanggotaan ini sudah ditutup. Riwayat tidak diubah.']);
        }
        try {
            $tempatkan->keluarkan($anggota, Carbon::today());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['anggota' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Siswa dikeluarkan dari kelas ini.']);

        return to_route('admin.kelas.index', ['kelas' => $kelas->id]);
    }

    public function tugaskanPengganti(Request $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', [Role::Guru->value, Role::Admin->value])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        GuruKelas::updateOrCreate(
            ['kelas_id' => $kelas->id, 'user_id' => $data['user_id']],
            ['tanggal_mulai' => $data['tanggal_mulai'] ?? null, 'tanggal_selesai' => $data['tanggal_selesai'] ?? null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guru pengganti ditugaskan.']);

        return to_route('admin.kelas.index', ['kelas' => $kelas->id]);
    }

    public function cabutPengganti(Kelas $kelas, GuruKelas $pengganti): RedirectResponse
    {
        abort_unless($pengganti->kelas_id === $kelas->id, 404);

        $pengganti->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan pengganti dicabut.']);

        return to_route('admin.kelas.index', ['kelas' => $kelas->id]);
    }

    /**
     * Isi satu kelas: anggota aktif, riwayat keanggotaan, dan penggantinya.
     *
     * @return array<string, mixed>|null
     */
    private function detail(int $kelasId): ?array
    {
        $kelas = Kelas::query()->with('kantor:id,nama')->find($kelasId);

        if ($kelas === null) {
            return null;
        }

        return [
            'id' => $kelas->id,
            'nama' => $kelas->nama,
            'kantor' => $kelas->kantor?->nama,
            'anggotas' => AnggotaKelas::query()
                ->where('kelas_id', $kelas->id)
                ->with('siswa:id,nis,nama')
                ->orderByDesc('is_active')
                ->orderBy('tanggal_mulai')
                ->get()
                ->map(fn (AnggotaKelas $a): array => [
                    'id' => $a->id,
                    'siswa_id' => $a->siswa_id,
                    'nis' => $a->siswa?->nis,
                    'nama' => $a->siswa?->nama,
                    'tanggal_mulai' => $a->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $a->tanggal_selesai?->toDateString(),
                    'is_active' => $a->is_active,
                ])
                ->all(),
            'penggantis' => GuruKelas::query()
                ->where('kelas_id', $kelas->id)
                ->with('guru:id,name')
                ->get()
                ->map(fn (GuruKelas $g): array => [
                    'id' => $g->id,
                    'user_id' => $g->user_id,
                    'nama' => $g->guru?->name,
                    'tanggal_mulai' => $g->tanggal_mulai?->toDateString(),
                    'tanggal_selesai' => $g->tanggal_selesai?->toDateString(),
                ])
                ->all(),
            'calonSiswas' => Siswa::query()
                ->where('kantor_id', $kelas->kantor_id)
                ->where('is_active', true)
                ->whereDoesntHave('keanggotaanKelas', function ($query): void {
                    $batas = Carbon::today()->startOfDay()->toDateTimeString();
                    $query->where('tanggal_mulai', '<=', $batas)
                        ->where(fn ($tanggal) => $tanggal->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $batas));
                })
                ->orderBy('nama')
                ->get(['id', 'nis', 'nama'])
                ->all(),
        ];
    }
}
