<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanOrangTuaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrangTuaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/OrangTua', [
            'orangTuas' => User::query()
                ->where('role', Role::OrangTua)
                ->with(['siswas' => fn ($query) => $query->orderBy('nama')->select('siswas.id', 'nama', 'nis')])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_active'])
                ->map(fn (User $orangTua): array => [
                    'id' => $orangTua->id,
                    'name' => $orangTua->name,
                    'email' => $orangTua->email,
                    'is_active' => $orangTua->is_active,
                    'anak' => $orangTua->siswas->map(fn ($siswa): array => [
                        'id' => $siswa->id,
                        'nama' => $siswa->nama,
                        'nis' => $siswa->nis,
                    ])->values(),
                ]),
            'passwordBaru' => session('password_baru'),
        ]);
    }

    public function store(SimpanOrangTuaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $orangTua = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $orangTua->forceFill([
            'role' => Role::OrangTua,
            'nip' => null,
            'kantor_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun orang tua '.$orangTua->name.' dibuat.']);

        return to_route('admin.orang-tua.index');
    }

    public function update(Request $request, User $orangTua): RedirectResponse
    {
        $this->pastikanOrangTua($orangTua);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($orangTua->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $orangTua->forceFill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun orang tua diperbarui.']);

        return back();
    }

    public function resetPassword(User $orangTua): RedirectResponse
    {
        $this->pastikanOrangTua($orangTua);

        $password = Str::password(12, symbols: false);
        $orangTua->forceFill(['password' => $password])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password '.$orangTua->name.' direset.']);

        return back()->with('password_baru', [
            'nama' => $orangTua->name,
            'email' => $orangTua->email,
            'password' => $password,
        ]);
    }

    public function destroy(User $orangTua): RedirectResponse
    {
        $this->pastikanOrangTua($orangTua);

        $nama = $orangTua->name;
        $orangTua->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun orang tua '.$nama.' dihapus.']);

        return back();
    }

    private function pastikanOrangTua(User $orangTua): void
    {
        abort_unless($orangTua->role === Role::OrangTua, 404);
    }
}
