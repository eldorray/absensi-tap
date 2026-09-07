<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanKantorRequest;
use App\Models\Kantor;
use App\Models\Lokasi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KantorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Kantor', [
            'kantors' => Kantor::query()
                ->withCount([
                    'users as jumlah_guru' => fn ($query) => $query->where('role', Role::Guru),
                    'lokasis as jumlah_lokasi',
                ])
                ->orderBy('nama')
                ->get()
                ->map(fn (Kantor $kantor): array => [
                    'id' => $kantor->id,
                    'nama' => $kantor->nama,
                    'jenjang' => $kantor->jenjang,
                    'alamat' => $kantor->alamat,
                    'is_active' => $kantor->is_active,
                    'jumlah_guru' => (int) $kantor->getAttribute('jumlah_guru'),
                    'jumlah_lokasi' => (int) $kantor->getAttribute('jumlah_lokasi'),
                ])
                ->all(),
            'lokasis' => Lokasi::query()
                ->orderBy('nama')
                ->get(['id', 'kantor_id', 'nama', 'radius_meter', 'is_active']),
            'gurus' => User::query()
                ->where('role', Role::Guru)
                ->orderBy('name')
                ->get(['id', 'name', 'nip', 'kantor_id']),
        ]);
    }

    public function store(SimpanKantorRequest $request): RedirectResponse
    {
        Kantor::create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kantor ditambahkan.']);

        return to_route('admin.kantor.index');
    }

    public function update(SimpanKantorRequest $request, Kantor $kantor): RedirectResponse
    {
        $kantor->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kantor diperbarui.']);

        return to_route('admin.kantor.index');
    }

    /**
     * Hapus kantor. Guru dan lokasinya tidak ikut terhapus, hanya lepas
     * penugasan (nullOnDelete) supaya absen tetap jalan.
     */
    public function destroy(Kantor $kantor): RedirectResponse
    {
        $kantor->delete();
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kantor dihapus. Guru dan lokasinya kembali tanpa kantor.',
        ]);

        return to_route('admin.kantor.index');
    }
}
