<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanLanggananPushRequest;
use App\Notifications\IzinDiajukan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\WebPushChannel;

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

    /**
     * Kirim push uji ke semua perangkat admin ini secara langsung (tanpa defer),
     * supaya jawaban layanan push bisa ditampilkan apa adanya.
     */
    public function tes(Request $request, WebPushChannel $channel): RedirectResponse
    {
        $laporan = $channel->send($request->user(), new IzinDiajukan(
            'Tes notifikasi',
            'Kalau ini muncul, notifikasi izin sudah berjalan.',
            route('admin.dashboard'),
        ));
        $gagal = array_values(array_filter($laporan, fn (MessageSentReport $report): bool => ! $report->isSuccess()));

        Inertia::flash('toast', match (true) {
            $laporan === [] => ['type' => 'error', 'message' => 'Belum ada perangkat yang berlangganan. Tekan Aktifkan dulu.'],
            $gagal === [] => ['type' => 'success', 'message' => 'Diterima layanan push untuk '.count($laporan).' perangkat. Kalau tidak muncul, cek pengaturan notifikasi di HP.'],
            default => ['type' => 'error', 'message' => Str::limit(sprintf(
                'Ditolak layanan push (%s): %s',
                $gagal[0]->getResponse()?->getStatusCode() ?? 'tanpa respons',
                (string) $gagal[0]->getResponse()?->getBody() ?: $gagal[0]->getReason(),
            ), 250)],
        });

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->deletePushSubscription($request->string('endpoint')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notifikasi dimatikan di perangkat ini.']);

        return back();
    }
}
