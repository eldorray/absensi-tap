<?php

namespace App\Http\Controllers\OrangTua;

use App\Enums\StatusIzin;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrangTua\AjukanIzinRequest;
use App\Models\IzinOrangTua;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Notifications\IzinDiajukan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IzinController extends Controller
{
    public function index(Request $request): Response
    {
        $tahunAjaran = TahunAjaran::aktif();

        return Inertia::render('orang-tua/Izin', [
            'tanggalMinimum' => today()->toDateString(),
            'tanggalMaximum' => $tahunAjaran?->tanggal_selesai->toDateString(),
            'anak' => $request->user()->siswas()
                ->where('siswas.is_active', true)
                ->orderBy('nama')
                ->get(['siswas.id', 'siswas.nama', 'siswas.nis'])
                ->map(fn (Siswa $siswa): array => [
                    'id' => $siswa->id,
                    'nama' => $siswa->nama,
                    'nis' => $siswa->nis,
                ])
                ->values(),
            'izins' => IzinOrangTua::query()
                ->where('user_id', $request->user()->id)
                ->with('siswa:id,nama,nis')
                ->latest()
                ->get()
                ->map(fn (IzinOrangTua $izin): array => [
                    'id' => $izin->id,
                    'siswa' => $izin->siswa->nama,
                    'nis' => $izin->siswa->nis,
                    'tipe' => $izin->tipe->value,
                    'tanggal_mulai' => $izin->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $izin->tanggal_selesai->toDateString(),
                    'alasan' => $izin->alasan,
                    'status' => $izin->status->value,
                    'catatan_review' => $izin->catatan_review,
                    'ada_lampiran' => $izin->lampiran_path !== null,
                ])
                ->values(),
        ]);
    }

    public function store(AjukanIzinRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['siswa_id', 'tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);
        $lampiranPath = $request->file('lampiran')?->store('izin-orang-tua', 'local');
        $data['lampiran_path'] = $lampiranPath;
        $data['user_id'] = $request->user()->id;
        $data['nama_pengaju'] = $request->user()->name;
        $data['email_pengaju'] = $request->user()->email;

        try {
            $izin = DB::transaction(function () use ($data): IzinOrangTua {
                Siswa::query()->whereKey($data['siswa_id'])->lockForUpdate()->firstOrFail();

                $bertabrakan = IzinOrangTua::query()
                    ->where('siswa_id', $data['siswa_id'])
                    ->whereIn('status', [StatusIzin::Pending, StatusIzin::Disetujui])
                    ->where('tanggal_mulai', '<=', $data['tanggal_selesai'])
                    ->where('tanggal_selesai', '>=', $data['tanggal_mulai'])
                    ->exists();

                if ($bertabrakan) {
                    throw ValidationException::withMessages([
                        'tanggal_mulai' => 'Sudah ada pengajuan untuk anak ini pada rentang tanggal tersebut.',
                    ]);
                }

                return IzinOrangTua::create($data);
            });
        } catch (Throwable $throwable) {
            if ($lampiranPath !== null) {
                Storage::disk('local')->delete($lampiranPath);
            }

            throw $throwable;
        }

        IzinDiajukan::dariIzinOrangTua($izin)->kirimKeAdmin();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan izin anak terkirim.']);

        return to_route('orang-tua.izin.index');
    }

    public function lampiran(IzinOrangTua $izinOrangTua): StreamedResponse
    {
        abort_if($izinOrangTua->lampiran_path === null, 404);

        return Storage::disk('local')->download($izinOrangTua->lampiran_path);
    }
}
