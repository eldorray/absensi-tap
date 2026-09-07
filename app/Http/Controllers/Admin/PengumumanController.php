<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanPengumumanRequest;
use App\Models\Pengumuman;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PengumumanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Pengumuman', [
            'pengumumans' => Pengumuman::query()
                ->latest()
                ->get()
                ->map(fn (Pengumuman $p): array => [
                    'id' => $p->id,
                    'judul' => $p->judul,
                    'isi' => $p->isi,
                    'is_active' => $p->is_active,
                    'dibuat' => $p->created_at?->format('d M Y'),
                ])
                ->all(),
        ]);
    }

    public function store(SimpanPengumumanRequest $request): RedirectResponse
    {
        Pengumuman::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman ditambahkan.']);

        return to_route('admin.pengumuman.index');
    }

    public function update(SimpanPengumumanRequest $request, Pengumuman $pengumuman): RedirectResponse
    {
        $pengumuman->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman diperbarui.']);

        return to_route('admin.pengumuman.index');
    }

    public function destroy(Pengumuman $pengumuman): RedirectResponse
    {
        $pengumuman->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman dihapus.']);

        return to_route('admin.pengumuman.index');
    }
}
