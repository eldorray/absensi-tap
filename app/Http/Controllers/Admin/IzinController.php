<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusIzin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewIzinRequest;
use App\Models\Izin;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IzinController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Izin', [
            'izins' => Izin::query()
                ->with('user:id,name,nip')
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (Izin $izin): array => [
                    'id' => $izin->id,
                    'guru' => $izin->user->name,
                    'nip' => $izin->user->nip,
                    'tipe' => $izin->tipe->value,
                    'tanggal_mulai' => $izin->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $izin->tanggal_selesai->toDateString(),
                    'alasan' => $izin->alasan,
                    'status' => $izin->status->value,
                    'catatan_review' => $izin->catatan_review,
                    'ada_lampiran' => $izin->lampiran_path !== null,
                ])
                ->all(),
        ]);
    }

    public function update(ReviewIzinRequest $request, Izin $izin): RedirectResponse
    {
        $izin->forceFill([
            'status' => StatusIzin::from($request->string('status')->toString()),
            'catatan_review' => $request->input('catatan_review'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Izin diperbarui.']);

        return to_route('admin.izin.index');
    }
}
