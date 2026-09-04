<?php

namespace App\Http\Controllers;

use App\Models\JadwalKerja;
use Inertia\Inertia;
use Inertia\Response;

class JadwalSayaController extends Controller
{
    /**
     * Tampilkan jadwal kerja mingguan untuk guru.
     */
    public function index(): Response
    {
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return Inertia::render('jadwal/Index', [
            'hariIni' => now()->dayOfWeek,
            'jadwals' => JadwalKerja::query()
                ->orderBy('day_of_week')
                ->get()
                ->map(fn (JadwalKerja $jadwal): array => [
                    'day_of_week' => $jadwal->day_of_week,
                    'nama_hari' => $namaHari[$jadwal->day_of_week],
                    'jam_masuk' => substr($jadwal->jam_masuk, 0, 5),
                    'jam_pulang' => substr($jadwal->jam_pulang, 0, 5),
                    'toleransi_menit' => $jadwal->toleransi_menit,
                    'is_hari_kerja' => $jadwal->is_hari_kerja,
                ])
                ->all(),
        ]);
    }
}
