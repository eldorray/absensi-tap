<?php

namespace App\Http\Middleware;

use App\Models\Kelas;
use App\Models\PengaturanAplikasi;
use App\Models\TahunAjaran;
use App\Support\TahunAjaranTerpilih;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Nama dan logo diambil dari pengaturan, bukan config: sekolah
            // mengubahnya sendiri dari layar tanpa menyentuh .env.
            'name' => fn (): string => PengaturanAplikasi::current()->nama,
            'versi' => config('app.version'),
            'aplikasi' => fn (): array => [
                'nama' => PengaturanAplikasi::current()->nama,
                'logo_url' => PengaturanAplikasi::current()->logoUrl(),
            ],
            'auth' => [
                'user' => $request->user(),
                // Role dipakai frontend untuk memilih shell; keputusan izin
                // tetap di gate/policy backend.
                'role' => $request->user()?->role->value,
                // This flag only controls menu visibility. Authorization remains
                // enforced by the `admin` gate on the route group.
                'isAdmin' => (bool) $request->user()?->can('admin'),
                'punyaKelas' => fn (): bool => $request->user() !== null
                    && Kelas::query()->diampuOleh($request->user())->where('is_active', true)->exists(),
            ],
            // Penanda tahun ajaran: is_active false berarti admin sedang
            // menengok tahun lain, dan setiap angka di layar bukan tahun aktif.
            'tahunAjaran' => fn (): ?array => $this->tahunAjaran(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{nama: string, is_active: bool}|null
     */
    private function tahunAjaran(): ?array
    {
        $id = app(TahunAjaranTerpilih::class)->id();

        if ($id === null) {
            return null;
        }

        $tahun = TahunAjaran::query()->whereKey($id)->first();

        return $tahun === null ? null : [
            'nama' => $tahun->nama,
            'is_active' => $tahun->is_active,
        ];
    }
}
