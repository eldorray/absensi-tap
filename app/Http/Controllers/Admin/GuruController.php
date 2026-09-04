<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatusPerangkat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanGuruRequest;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GuruController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Guru', ['gurus' => User::query()->where('role', Role::Guru)->with(['perangkats' => fn ($q) => $q->latest()])->orderBy('name')->get()->map(fn (User $g) => ['id' => $g->id, 'name' => $g->name, 'nip' => $g->nip, 'email' => $g->email, 'role' => $g->role->value, 'is_active' => $g->is_active, 'perangkats' => $g->perangkats->map(fn (Perangkat $p) => ['id' => $p->id, 'label' => $p->label, 'status' => $p->status->value, 'terdaftar' => $p->created_at?->format('d M Y')])->all()])->all()]);
    }

    public function store(SimpanGuruRequest $request): RedirectResponse
    {
        $g = User::create($request->safe()->only(['name', 'nip', 'email', 'password']));
        $g->forceFill(['role' => Role::Guru, 'is_active' => true, 'email_verified_at' => now()])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun guru dibuat.']);

        return to_route('admin.guru.index');
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
