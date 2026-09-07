<?php

namespace App\Http\Controllers;

use App\Models\JadwalKerja;
use App\Models\PengaturanAbsensi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JadwalSayaController extends Controller
{
    /**
     * Tampilkan jadwal kerja mingguan milik guru yang sedang masuk.
     */
    public function index(Request $request): Response
    {
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $pengaturan = PengaturanAbsensi::current();
        $peta = JadwalKerja::untukGuru((int) $request->user()->id);
        $jadwals = [];

        foreach (range(0, 6) as $day) {
            $jadwal = $peta[$day] ?? null;

            if ($jadwal === null) {
                continue;
            }

            $jadwals[] = [
                'day_of_week' => $day,
                'nama_hari' => $namaHari[$day],
                'jam_masuk' => substr($jadwal->jam_masuk, 0, 5),
                'jam_pulang' => substr($jadwal->jam_pulang, 0, 5),
                'is_hari_kerja' => $jadwal->is_hari_kerja,
            ];
        }

        return Inertia::render('jadwal/Index', [
            'hariIni' => now()->dayOfWeek,
            'jadwals' => $jadwals,
            'toleransiMenit' => $pengaturan->toleransi_menit,
        ]);
    }
}
