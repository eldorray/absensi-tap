<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Absensi\RekapBulanan;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\PengaturanAplikasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapController extends Controller
{
    /**
     * Batas panjang rekap periode, dalam hari. Setahun cukup untuk satu tahun
     * ajaran, dan menahan query agar tidak menyapu seluruh riwayat sekaligus.
     */
    public const MAKS_HARI_PERIODE = 366;

    private const NAMA_BULAN = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public function index(Request $request, RekapBulanan $rekapBulanan): Response
    {
        $filter = $this->filter($request);
        $rekap = $rekapBulanan->periode($filter['mulai'], $filter['selesai'], $filter['user_id']);

        if ($filter['mode'] === 'periode') {
            // Periode hanya menampilkan ringkasan per guru; grid per tanggal
            // untuk rentang panjang terlalu berat dikirim ke peramban.
            $rekap = [
                'tanggals' => [],
                'baris' => array_map(fn (array $baris): array => [...$baris, 'hari' => []], $rekap['baris']),
            ];
        }

        return Inertia::render('admin/Rekap', [
            'filter' => [
                'mode' => $filter['mode'],
                'tahun' => $filter['tahun'],
                'bulan' => $filter['bulan'],
                'mulai' => $filter['mulai']->toDateString(),
                'selesai' => $filter['selesai']->toDateString(),
                'user_id' => $filter['user_id'],
            ],
            'gurus' => User::query()
                ->where('role', Role::Guru)
                ->orderBy('name')
                ->get(['id', 'name']),
            'rekap' => $rekap,
        ]);
    }

    /**
     * Laporan siap cetak.
     *
     * Dirender sebagai HTML, bukan berkas PDF dari server: aplikasi ini belum
     * memuat pustaka PDF apa pun, dan "Simpan sebagai PDF" di peramban
     * menghasilkan berkas yang sama tanpa menambah dependensi.
     */
    public function cetak(Request $request, RekapBulanan $rekapBulanan): View
    {
        $filter = $this->filter($request);
        $rekap = $rekapBulanan->periode($filter['mulai'], $filter['selesai'], $filter['user_id']);

        return view('admin.rekap-cetak', [
            'aplikasi' => PengaturanAplikasi::current(),
            'periode' => $this->labelPeriode($filter),
            'dicetak' => now()->translatedFormat('d F Y H:i'),
            'baris' => $rekap['baris'],
            // Jumlah, bukan maksimum: tiap guru punya jadwal sendiri, jadi
            // persentase total = seluruh kehadiran / seluruh hari efektif.
            'totalHariEfektif' => collect($rekap['baris'])->sum('hari_efektif'),
        ]);
    }

    public function export(Request $request, RekapBulanan $rekapBulanan): StreamedResponse
    {
        $filter = $this->filter($request);
        $rekap = $rekapBulanan->periode($filter['mulai'], $filter['selesai'], $filter['user_id']);
        $periode = $filter['mode'] === 'periode';
        $nama = $periode
            ? sprintf('rekap-absensi-%s-sd-%s.csv', $filter['mulai']->toDateString(), $filter['selesai']->toDateString())
            : sprintf('rekap-absensi-%04d-%02d.csv', $filter['tahun'], $filter['bulan']);

        return response()->streamDownload(function () use ($rekap, $periode): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                throw new \RuntimeException('Gagal membuka keluaran CSV.');
            }

            fwrite($keluaran, "\xEF\xBB\xBF");

            if ($periode) {
                fputcsv($keluaran, ['NIP', 'Nama', 'Hari efektif', 'Hadir', 'Terlambat', 'Menit terlambat', 'Izin', 'Sakit', 'Cuti', 'Alfa', '% Kehadiran']);

                foreach ($rekap['baris'] as $baris) {
                    fputcsv($keluaran, [
                        $baris['nip'] ?? '',
                        $baris['nama'],
                        $baris['hari_efektif'],
                        $baris['ringkasan']['hadir'] ?? 0,
                        $baris['terlambat'],
                        $baris['menit_terlambat'],
                        $baris['ringkasan']['izin'] ?? 0,
                        $baris['ringkasan']['sakit'] ?? 0,
                        $baris['ringkasan']['cuti'] ?? 0,
                        $baris['ringkasan']['alfa'] ?? 0,
                        $baris['persentase'],
                    ]);
                }
            } else {
                fputcsv($keluaran, ['NIP', 'Nama', ...$rekap['tanggals']]);

                foreach ($rekap['baris'] as $baris) {
                    fputcsv($keluaran, [
                        $baris['nip'] ?? '',
                        $baris['nama'],
                        ...array_map(fn (array $hari): string => $hari['label'], $baris['hari']),
                    ]);
                }
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array{mode: string, tahun: int, bulan: int, mulai: Carbon, selesai: Carbon, user_id: int|null}  $filter
     */
    private function labelPeriode(array $filter): string
    {
        if ($filter['mode'] === 'bulanan') {
            return self::NAMA_BULAN[$filter['bulan']].' '.$filter['tahun'];
        }

        return $filter['mulai']->translatedFormat('j F Y').' – '.$filter['selesai']->translatedFormat('j F Y');
    }

    /**
     * Bulanan memakai tahun + bulan; periode memakai tanggal mulai-selesai.
     * Keduanya diterjemahkan jadi satu rentang tanggal untuk RekapBulanan.
     *
     * @return array{mode: string, tahun: int, bulan: int, mulai: Carbon, selesai: Carbon, user_id: int|null}
     */
    private function filter(Request $request): array
    {
        $data = $request->validate([
            'mode' => ['nullable', 'in:bulanan,periode'],
            'tahun' => ['nullable', 'integer', 'between:2020,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'mulai' => ['required_if:mode,periode', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01'],
            'selesai' => [
                'required_if:mode,periode',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:mulai',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    // createFromFormat, bukan parse: aturan ini tetap jalan walau
                    // format tanggalnya gagal, dan parse akan melempar exception.
                    $mulai = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->input('mulai'));
                    $selesai = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

                    if ($mulai !== false && $selesai !== false && $mulai->diff($selesai)->days >= self::MAKS_HARI_PERIODE) {
                        $fail('Periode rekap paling panjang '.self::MAKS_HARI_PERIODE.' hari.');
                    }
                },
            ],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $mode = $data['mode'] ?? 'bulanan';
        $tahun = (int) ($data['tahun'] ?? now()->year);
        $bulan = (int) ($data['bulan'] ?? now()->month);

        if ($mode === 'periode') {
            $mulai = Carbon::parse($data['mulai']);
            $selesai = Carbon::parse($data['selesai']);
        } else {
            $mulai = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $selesai = $mulai->copy()->endOfMonth();
        }

        return [
            'mode' => $mode,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
        ];
    }
}
