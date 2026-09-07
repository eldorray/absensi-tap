<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Guru\ImporGuru;
use App\Enums\Role;
use App\Enums\StatusPerangkat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImporGuruRequest;
use App\Http\Requests\Admin\SimpanGuruRequest;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuruController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Guru', ['hasilImpor' => session('impor_guru'), 'passwordBaru' => session('password_baru'), 'gurus' => User::query()->where('role', Role::Guru)->with(['perangkats' => fn ($q) => $q->latest()])->orderBy('name')->get()->map(fn (User $g) => ['id' => $g->id, 'name' => $g->name, 'nip' => $g->nip, 'email' => $g->email, 'role' => $g->role->value, 'is_active' => $g->is_active, 'perangkats' => $g->perangkats->map(fn (Perangkat $p) => ['id' => $p->id, 'label' => $p->label, 'status' => $p->status->value, 'terdaftar' => $p->created_at?->format('d M Y')])->all()])->all()]);
    }

    public function store(SimpanGuruRequest $request): RedirectResponse
    {
        $g = User::create($request->safe()->only(['name', 'nip', 'email', 'password']));
        $g->forceFill(['role' => Role::Guru, 'is_active' => true, 'email_verified_at' => now()])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun guru dibuat.']);

        return to_route('admin.guru.index');
    }

    /**
     * Berkas contoh untuk diisi di Excel lalu diunggah kembali.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                throw new \RuntimeException('Gagal membuka keluaran CSV.');
            }

            // BOM supaya Excel membaca huruf beraksen dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, ImporGuru::KOLOM);
            // Baris contoh sengaja menunjukkan bahwa email dan password boleh
            // dikosongkan: yang wajib hanya nama.
            fputcsv($keluaran, ['Siti Aminah', '198501012010012001', '', '']);
            fputcsv($keluaran, ['Ahmad Fauzi', '', 'ahmad@sekolah.sch.id', 'RahasiaKuat123']);

            fclose($keluaran);
        }, 'template-impor-guru.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function impor(ImporGuruRequest $request, ImporGuru $imporGuru): RedirectResponse
    {
        $hasil = $imporGuru($request->file('berkas')->getRealPath());

        Inertia::flash('toast', [
            'type' => $hasil['dibuat'] > 0 ? 'success' : 'error',
            'message' => $hasil['dibuat'].' akun dibuat, '.$hasil['dilewati'].' dilewati.',
        ]);

        return to_route('admin.guru.index')->with('impor_guru', $hasil);
    }

    public function update(Request $request, User $guru): RedirectResponse
    {
        $guru->forceFill($request->validate(['is_active' => ['required', 'boolean'], 'role' => ['sometimes', Rule::enum(Role::class)]]))->save();

        return to_route('admin.guru.index');
    }

    public function updatePerangkat(Request $request, Perangkat $perangkat): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(StatusPerangkat::class)->only([StatusPerangkat::Active, StatusPerangkat::Revoked])]]);
        $status = StatusPerangkat::from($data['status']);
        DB::transaction(function () use ($request, $perangkat, $status): void {
            if ($status === StatusPerangkat::Active) {
                Perangkat::query()->where('user_id', $perangkat->user_id)->whereKeyNot($perangkat->id)->where('status', StatusPerangkat::Active)->update(['status' => StatusPerangkat::Revoked->value]);
            } $perangkat->forceFill(['status' => $status, 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        });

        return to_route('admin.guru.index');
    }
}
