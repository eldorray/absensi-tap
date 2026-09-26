<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusIzin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewIzinOrangTuaRequest;
use App\Models\IzinOrangTua;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IzinOrangTuaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/IzinOrangTua', [
            'izins' => IzinOrangTua::query()
                ->with('siswa:id,nama,nis')
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->latest()
                ->get()
                ->map(fn (IzinOrangTua $izin): array => [
                    'id' => $izin->id,
                    'orang_tua' => $izin->nama_pengaju,
                    'email_orang_tua' => $izin->email_pengaju,
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

    public function lampiran(IzinOrangTua $izinOrangTua): StreamedResponse
    {
        abort_if($izinOrangTua->lampiran_path === null, 404);

        return Storage::disk('local')->download($izinOrangTua->lampiran_path);
    }

    public function update(ReviewIzinOrangTuaRequest $request, IzinOrangTua $izinOrangTua): RedirectResponse
    {
        DB::transaction(function () use ($request, $izinOrangTua): void {
            $izinTerkunci = IzinOrangTua::query()->lockForUpdate()->findOrFail($izinOrangTua->id);

            if ($izinTerkunci->status !== StatusIzin::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ini sudah direview dan tidak dapat diubah lagi.',
                ]);
            }

            $izinTerkunci->forceFill([
                'status' => StatusIzin::from($request->string('status')->toString()),
                'catatan_review' => $request->input('catatan_review'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan orang tua diperbarui.']);

        return to_route('admin.izin-orang-tua.index');
    }
}
