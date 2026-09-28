<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanLanggananPushRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Langganan Web Push perangkat admin, supaya izin baru masuk sebagai notifikasi HP.
 */
class LanggananPushController extends Controller
{
    public function store(SimpanLanggananPushRequest $request): RedirectResponse
    {
        $request->user()->updatePushSubscription(
            $request->string('endpoint')->toString(),
            $request->string('keys.p256dh')->toString(),
            $request->string('keys.auth')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi aktif di perangkat ini.']);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->deletePushSubscription($request->string('endpoint')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi dimatikan di perangkat ini.']);

        return back();
    }
}
