<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role yang dikenal aplikasi beserta pemegangnya.
 *
 * Rolenya tetap ditetapkan di kode (enum), bukan tabel: otorisasi setiap route
 * admin bergantung padanya, dan role yang bisa dikarang bebas berarti izin yang
 * bisa dikarang bebas juga. Yang bisa diubah di sini adalah siapa memegang apa.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Role', [
            'roles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'keterangan' => $role->keterangan(),
                'akses' => $role->akses(),
                'jumlah' => User::query()->where('role', $role)->count(),
                'jumlah_aktif' => User::query()->where('role', $role)->where('is_active', true)->count(),
                'pemegang' => User::query()
                    ->where('role', $role)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'is_active'])
                    ->all(),
            ], Role::cases()),
        ]);
    }
}
