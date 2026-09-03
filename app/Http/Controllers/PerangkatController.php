<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\DaftarkanPerangkat;
use App\Enums\StatusPerangkat;
use App\Http\Requests\DaftarkanPerangkatRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PerangkatController extends Controller
{
    /**
     * Ikat HP yang dipakai guru saat ini ke akunnya.
     */
    public function store(DaftarkanPerangkatRequest $request, DaftarkanPerangkat $daftarkan): RedirectResponse
    {
        $perangkat = $daftarkan(
            $request->user(),
            $request->string('device_uuid')->toString(),
            $request->userAgent(),
        );

        $aktif = $perangkat->status === StatusPerangkat::Active;

        Inertia::flash('toast', [
            'type' => $aktif ? 'success' : 'info',
            'message' => $aktif
                ? 'HP ini berhasil diikat ke akunmu.'
                : 'Permintaan ganti HP dikirim. Tunggu persetujuan TU.',
        ]);

        // Cookie ini cadangan localStorage, bukan lapisan keamanan: nilainya sama
        // persis. Gunanya supaya guru tidak terlihat sebagai perangkat baru ketika
        // penyimpanan browser dibersihkan atau dievakuasi iOS.
        return to_route('dashboard')->withCookie(
            cookie()->forever('perangkat_uuid', $perangkat->uuid, null, null, null, true, false, 'lax')
        );
    }
}
