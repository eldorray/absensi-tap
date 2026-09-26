<?php

namespace App\Http\Responses;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();
        $tujuan = $user?->role === Role::Admin
            ? route('admin.dashboard', absolute: false)
            : route('aplikasi', absolute: false);

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $user !== null && $this->bolehDituju($user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($tujuan);
    }

    /**
     * URL intended bisa tertinggal dari sesi akun lain di perangkat yang sama
     * atau dari start_url PWA lama. Hanya ikuti bila memang wilayah role ini,
     * supaya orang tua tidak dilempar ke halaman pegawai lalu kena 403.
     */
    private function bolehDituju(User $user, string $intended): bool
    {
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $wilayahOrangTua = $path === '/orang-tua' || str_starts_with($path, '/orang-tua/');
        $wilayahAdmin = $path === '/admin' || str_starts_with($path, '/admin/');

        return match ($user->role) {
            Role::OrangTua => $wilayahOrangTua || str_starts_with($path, '/settings'),
            Role::Admin => ! $wilayahOrangTua,
            default => ! $wilayahOrangTua && ! $wilayahAdmin,
        };
    }
}
