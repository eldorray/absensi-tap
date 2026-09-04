<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatAbsensiController extends Controller
{
    /**
     * Tampilkan riwayat absensi 30 hari terakhir milik guru yang sedang login.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('riwayat/Index', [
            'riwayat' => Absensi::query()
                ->with(['masukAttempt', 'pulangAttempt'])
                ->where('user_id', $request->user()->id)
                ->whereDate('tanggal', '>=', today()->subDays(29))
                ->orderByDesc('tanggal')
                ->get()
                ->map(fn (Absensi $absensi): array => [
                    'tanggal' => $absensi->tanggal->toDateString(),
                    'status' => $absensi->status?->value,
                    'jam_masuk' => $absensi->masukAttempt?->created_at?->format('H:i'),
                    'jam_pulang' => $absensi->pulangAttempt?->created_at?->format('H:i'),
                    'pulang_cepat' => $absensi->pulang_cepat,
                    'terverifikasi' => $absensi->masukAttempt->terverifikasi ?? false,
                ])
                ->all(),
        ]);
    }
}
