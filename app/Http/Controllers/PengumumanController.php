<?php

namespace App\Http\Controllers;

use App\Models\Pengumuman;
use Inertia\Inertia;
use Inertia\Response;

class PengumumanController extends Controller
{
    /**
     * Seluruh pengumuman aktif tahun ajaran ini, terbaru dulu.
     *
     * Dashboard hanya memuat beberapa yang terakhir; halaman ini arsipnya.
     */
    public function index(): Response
    {
        return Inertia::render('pengumuman/Index', [
            'pengumumans' => Pengumuman::query()
                ->where('is_active', true)
                ->latest()
                ->latest('id')
                ->paginate(10)
                ->through(fn (Pengumuman $pengumuman): array => [
                    'id' => $pengumuman->id,
                    'judul' => $pengumuman->judul,
                    'isi' => $pengumuman->isi,
                    'dibuat' => $pengumuman->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
