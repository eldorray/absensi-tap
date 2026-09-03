<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjukanIzinRequest;
use App\Models\Izin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IzinController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('izin/Index', [
            'izins' => Izin::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('tanggal_mulai')
                ->get()
                ->map(fn (Izin $izin): array => [
                    'id' => $izin->id,
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

    public function store(AjukanIzinRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);

        // Disk 'local' bukan 'public': surat dokter tidak boleh bisa dibuka
        // dengan menebak URL.
        $data['lampiran_path'] = $request->file('lampiran')?->store('izin', 'local');
        $data['user_id'] = $request->user()->id;

        Izin::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan izin terkirim.']);

        return to_route('izin.index');
    }

    /**
     * Unduh lampiran. Otorisasi lewat IzinPolicy::view, bukan lewat URL rahasia.
     */
    public function lampiran(Izin $izin): StreamedResponse
    {
        abort_if($izin->lampiran_path === null, 404);

        return Storage::disk('local')->download($izin->lampiran_path);
    }
}
