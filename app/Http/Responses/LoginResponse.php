<?php

namespace App\Http\Responses;

use App\Enums\Role;
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

        $tujuan = $request->user()?->role === Role::Admin
            ? route('admin.dashboard', absolute: false)
            : route('aplikasi', absolute: false);

        return redirect()->intended($tujuan);
    }
}
