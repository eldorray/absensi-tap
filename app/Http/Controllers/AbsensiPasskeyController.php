<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;

class AbsensiPasskeyController extends Controller
{
    /**
     * Options WebAuthn untuk verifikasi ulang tepat sebelum tap.
     *
     * Session key 'passkey.verification_options' dipakai karena
     * Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest meng-hardcode
     * nama itu. Memakai key lain akan membuat verifikasi paket selalu gagal.
     */
    public function index(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $guru = $request->user();

        abort_unless(
            $guru instanceof PasskeyUser && $guru->hasPasskeysEnabled(),
            409,
            'Belum ada passkey terdaftar.',
        );

        $options = $generate($guru);

        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    /**
     * Terima assertion dari browser dan tandai session sebagai terverifikasi.
     *
     * Penanda ini berumur pendek dan sekali pakai; AbsensiController yang
     * mengonsumsinya.
     */
    public function store(PasskeyVerificationRequest $request, VerifyPasskey $verify): JsonResponse
    {
        $guru = $request->user();

        abort_unless($guru instanceof PasskeyUser, 409, 'Belum ada passkey terdaftar.');

        $verify($request->credential(), $request->verificationOptions(), $guru);

        $request->session()->put(CatatAbsensi::KEY_VERIFIKASI, now()->toIso8601String());

        return response()->json(['verified' => true]);
    }
}
