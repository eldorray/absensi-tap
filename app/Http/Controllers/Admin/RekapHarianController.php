<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Absensi\RekapHarian;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\User;
use App\Support\AnomaliAbsensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapHarianController extends Controller
{
    public function index(Request $request, RekapHarian $rekapHarian): Response
    {
        $tanggal = $this->tanggal($request);

        return Inertia::render('admin/RekapHarian', [
            'rekap' => $rekapHarian($tanggal),
            'labelAnomali' => AnomaliAbsensi::label(),
        ]);
    }

    public function export(Request $request, RekapHarian $rekapHarian): StreamedResponse
    {
        $tanggal = $this->tanggal($request);
        $rekap = $rekapHarian($tanggal);
        $labelAnomali = AnomaliAbsensi::label();

        return response()->streamDownload(function () use ($rekap, $labelAnomali): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                throw new \RuntimeException('Gagal membuka keluaran CSV.');
            }

            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, ['NIP', 'Nama', 'Jadwal', 'Masuk', 'Pulang', 'Status', 'Lokasi', 'Jarak (m)', 'Catatan']);

            foreach ($rekap['baris'] as $baris) {
                fputcsv($keluaran, [
                    $baris['nip'] ?? '',
                    $baris['nama'],
                    $baris['jadwal'] ?? '',
                    $baris['jam_masuk'] ?? '',
                    $baris['jam_pulang'] ?? '',
                    $baris['label'],
                    $baris['lokasi'] ?? '',
                    $baris['jarak_meter'] ?? '',
                    implode(', ', array_map(fn (string $kode): string => $labelAnomali[$kode] ?? $kode, $baris['anomali'])),
                ]);
            }

            fclose($keluaran);
        }, 'rekap-harian-'.$rekap['tanggal'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Hapus absen satu guru pada satu tanggal supaya dia bisa absen ulang.
     *
     * Barisnya di absensis yang dibuang; jejak di absensi_attempts sengaja
     * dibiarkan -- di situlah bukti jam, koordinat, dan perangkatnya, dan itu
     * yang membuat reset tetap bisa diaudit.
     */
    public function reset(Request $request, User $guru): RedirectResponse
    {
        abort_unless($guru->role === Role::Guru, 404);

        $tanggal = $this->tanggal($request);

        $terhapus = Absensi::query()
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', $tanggal)
            ->delete();

        Inertia::flash('toast', $terhapus > 0
            ? ['type' => 'success', 'message' => 'Absen '.$guru->name.' direset. Dia bisa absen ulang.']
            : ['type' => 'info', 'message' => 'Tidak ada absen '.$guru->name.' pada tanggal itu.']);

        return to_route('admin.rekap-harian.index', ['tanggal' => $tanggal->toDateString()]);
    }

    /**
     * Tanggal yang diminta, default hari ini.
     */
    private function tanggal(Request $request): Carbon
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return isset($data['tanggal'])
            ? Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay()
            // Carbon::today(), bukan today(): AppServiceProvider memasang
            // Date::use(CarbonImmutable::class) sedangkan RekapHarian menerima
            // Illuminate\Support\Carbon.
            : Carbon::today();
    }
}
