<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanUserRequest;
use App\Models\Kantor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola akun staf: guru maupun admin, beserta role dan kantornya.
 *
 * Halaman /admin/guru tetap ada untuk hal yang khas guru (perangkat, impor
 * Excel). Di sini yang diurus keanggotaannya: siapa, perannya apa, di kantor
 * mana, dan masih aktif atau tidak.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/User', [
            'users' => User::query()
                ->whereIn('role', [Role::Guru, Role::Admin])
                ->with('kantor:id,nama')
                ->orderBy('name')
                ->get(['id', 'name', 'nip', 'email', 'role', 'kantor_id', 'is_active'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nip' => $user->nip,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'kantor_id' => $user->kantor_id,
                    'kantor' => $user->kantor?->nama,
                    'is_active' => $user->is_active,
                ])
                ->all(),
            'kantors' => Kantor::query()->orderBy('nama')->get(['id', 'nama']),
            'passwordBaru' => session('password_baru'),
            'roles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ], [Role::Guru, Role::Admin]),
        ]);
    }

    public function store(SimpanUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'nip' => $data['nip'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->forceFill([
            'role' => Role::from($data['role']),
            'kantor_id' => $data['kantor_id'] ?? null,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun '.$user->name.' dibuat.']);

        return to_route('admin.user.index');
    }

    /**
     * Ubah role, kantor, dan status akun.
     *
     * @throws ValidationException
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->pastikanAkunStaf($user);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nip' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in([Role::Guru->value, Role::Admin->value])],
            'kantor_id' => ['sometimes', 'nullable', 'integer', 'exists:kantors,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->jagaAksesAdmin($request, $user, $data);

        foreach (['name', 'nip', 'email'] as $kolom) {
            if (array_key_exists($kolom, $data)) {
                $user->{$kolom} = $data[$kolom];
            }
        }

        if (array_key_exists('role', $data)) {
            $user->role = Role::from($data['role']);
        }

        if (array_key_exists('kantor_id', $data)) {
            $user->kantor_id = $data['kantor_id'];
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        return back();
    }

    /**
     * Buatkan password baru dan tampilkan sekali ke admin.
     *
     * Passwordnya tidak disimpan di mana pun selain hash-nya, jadi tidak ada
     * endpoint yang bisa dipanggil ulang untuk melihatnya lagi.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $this->pastikanAkunStaf($user);

        $password = Str::password(12, symbols: false);

        $user->forceFill(['password' => $password])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password '.$user->name.' direset.']);

        // back(), bukan to_route: tombolnya dipakai di /admin/user dan
        // /admin/guru, dan admin harus tetap di halaman tempat dia menekannya.
        return back()->with('password_baru', [
            'nama' => $user->name,
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    /**
     * Hapus akun beserta seluruh jejaknya.
     *
     * Absensi, percobaan tap, izin, jadwal khusus, perangkat, dan passkey ikut
     * terhapus (cascade). Karena itu penjaga yang sama dengan pencabutan akses
     * berlaku, dan pesan konfirmasinya di layar menyebut apa yang hilang.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->pastikanAkunStaf($user);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'Akun sendiri tidak bisa dihapus.',
            ]);
        }

        $this->jagaAksesAdmin($request, $user, ['role' => Role::Guru->value, 'is_active' => false]);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun '.$user->name.' dihapus.']);

        return back();
    }

    /**
     * Tolak perubahan yang bisa mengunci semua orang di luar aplikasi.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function jagaAksesAdmin(Request $request, User $user, array $data): void
    {
        $tetapAdmin = ! array_key_exists('role', $data) || Role::from($data['role']) === Role::Admin;
        $tetapAktif = ! array_key_exists('is_active', $data) || (bool) $data['is_active'];

        if ($user->role !== Role::Admin || ($tetapAdmin && $tetapAktif)) {
            return;
        }

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'role' => 'Akun sendiri tidak bisa dicabut aksesnya. Minta admin lain melakukannya.',
            ]);
        }

        $adminLain = User::query()
            ->where('role', Role::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $adminLain) {
            throw ValidationException::withMessages([
                'role' => 'Ini admin aktif terakhir. Tunjuk admin lain dulu sebelum mencabutnya.',
            ]);
        }
    }

    private function pastikanAkunStaf(User $user): void
    {
        abort_unless(in_array($user->role, [Role::Guru, Role::Admin], true), 404);
    }
}
