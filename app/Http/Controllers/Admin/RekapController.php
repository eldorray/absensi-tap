<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Absensi\RekapBulanan;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\PengaturanAplikasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapController extends Controller
{
    public function index(Request $request, RekapBulanan $rekapBulanan): Response
    {
        [$tahun, $bulan, $userId] = $this->filter($request);

        return Inertia::render('admin/Rekap', [
            'filter' => ['tahun' => $tahun, 'bulan' => $bulan, 'user_id' => $userId],
            'gurus' => User::query()
                ->where('role', Role::Guru)
                ->orderBy('name')
                ->get(['id', 'name']),
            'rekap' => $rekapBulanan($tahun, $bulan, $userId),
        ]);
    }

    /**
     * Laporan bulanan siap cetak.
     *
     * Dirender sebagai HTML, bukan berkas PDF dari server: aplikasi ini belum
     * memuat pustaka PDF apa pun, dan "Simpan sebagai PDF" di peramban
     * menghasilkan berkas yang sama tanpa menambah dependensi.
     */
    public function cetak(Request $request, RekapBulanan $rekapBulanan): View
    {
        [$tahun, $bulan, $userId] = $this->filter($request);
        $rekap = $rekapBulanan($tahun, $bulan, $userId);

        $namaBulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return view('admin.rekap-cetak', [
            'aplikasi' => PengaturanAplikasi::current(),
            'periode' => $namaBulan[$bulan].' '.$tahun,
            'dicetak' => now()->translatedFormat('d F Y H:i'),
            'baris' => $rekap['baris'],
            'totalHariEfektif' => collect($rekap['baris'])->max('hari_efektif') ?? 0,
        ]);
    }

    public function export(Request $request, RekapBulanan $rekapBulanan): StreamedResponse
    {
        [$tahun, $bulan, $userId] = $this->filter($request);
        $rekap = $rekapBulanan($tahun, $bulan, $userId);
        $nama = sprintf('rekap-absensi-%04d-%02d.csv', $tahun, $bulan);

        return response()->streamDownload(function () use ($rekap): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                throw new \RuntimeException('Gagal membuka keluaran CSV.');
            }

            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, ['NIP', 'Nama', ...$rekap['tanggals']]);

            foreach ($rekap['baris'] as $baris) {
                fputcsv($keluaran, [
                    $baris['nip'] ?? '',
                    $baris['nama'],
                    ...array_map(fn (array $hari): string => $hari['label'], $baris['hari']),
                ]);
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: int, 1: int, 2: int|null} */
    private function filter(Request $request): array
    {
        $data = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2020,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return [
            (int) ($data['tahun'] ?? now()->year),
            (int) ($data['bulan'] ?? now()->month),
            isset($data['user_id']) ? (int) $data['user_id'] : null,
        ];
    }
}
