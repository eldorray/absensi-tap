<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Absensi\RekapHarian;
use App\Enums\HasilTap;
use App\Enums\Role;
use App\Enums\StatusIzin;
use App\Enums\StatusPerangkat;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\IzinOrangTua;
use App\Models\Kantor;
use App\Models\Lokasi;
use App\Models\Perangkat;
use App\Models\Siswa;
use App\Models\User;
use App\Support\AnomaliAbsensi;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ringkasan satu layar untuk admin: keadaan hari ini, hal yang menunggu
 * tindakan, dan jejak tap terbaru.
 *
 * Angka hari ini diambil dari RekapHarian supaya cocok betul dengan halaman
 * rekap harian -- dashboard yang berbeda satu angka dengan halaman rekapnya
 * membuat keduanya tidak dipercaya.
 */
class DashboardController extends Controller
{
    /**
     * Banyaknya jejak tap terbaru yang ditampilkan.
     */
    private const JUMLAH_LOG = 12;

    public function index(RekapHarian $rekapHarian): Response
    {
        $hariIni = $rekapHarian(Carbon::today());

        return Inertia::render('admin/Dashboard', [
            'tanggal' => $hariIni['tanggal'],
            'ringkasanHariIni' => $hariIni['ringkasan'],
            'perluTindakan' => [
                'izin_menunggu' => Izin::query()->where('status', StatusIzin::Pending)->count(),
                // Hanya HP guru: tautannya menuju halaman Guru, satu-satunya tempat
                // persetujuan HP. HP akun admin tidak tampil di sana, jadi kalau
                // ikut dihitung peringatannya tidak pernah bisa diselesaikan.
                'perangkat_menunggu' => Perangkat::query()
                    ->where('status', StatusPerangkat::Pending)
                    ->whereHas('user', fn ($query) => $query->where('role', Role::Guru))
                    ->count(),
                'guru_tanpa_kantor' => User::query()->where('role', Role::Guru)->whereNull('kantor_id')->count(),
                'guru_nonaktif' => User::query()->where('role', Role::Guru)->where('is_active', false)->count(),
                'izin_orang_tua_menunggu' => IzinOrangTua::query()->where('status', StatusIzin::Pending)->count(),
                // Kelas hari ini pada tahun ajaran yang dilihat -- sama dengan
                // filter "Belum ada kelas" di halaman Siswa yang dituju tautannya.
                'siswa_tanpa_kelas' => Siswa::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', AnggotaKelas::query()->berlakuPada(today())->whereHas('kelas')->select('siswa_id'))
                    ->count(),
                'orang_tua_belum_tertaut' => User::query()
                    ->where('role', Role::OrangTua)
                    ->where('is_active', true)
                    ->whereDoesntHave('siswas')
                    ->count(),
            ],
            'master' => [
                'guru' => User::query()->where('role', Role::Guru)->count(),
                'admin' => User::query()->where('role', Role::Admin)->count(),
                'kantor' => Kantor::query()->count(),
                'lokasi_aktif' => Lokasi::query()->where('is_active', true)->count(),
            ],
            'bulanIni' => $this->bulanIni(),
            'log' => $this->log(),
            'labelAnomali' => AnomaliAbsensi::label(),
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Jumlah absensi bulan ini per status.
     *
     * @return array{hadir: int, terlambat: int, pulang_cepat: int, belum_tap_pulang: int}
     */
    private function bulanIni(): array
    {
        $mulai = Carbon::today()->startOfMonth();
        $selesai = Carbon::today()->endOfMonth();

        $absensis = Absensi::query()
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->get(['status', 'pulang_cepat', 'pulang_attempt_id']);

        return [
            'hadir' => $absensis->where('status.value', 'hadir')->count(),
            'terlambat' => $absensis->where('status.value', 'terlambat')->count(),
            'pulang_cepat' => $absensis->where('pulang_cepat', true)->count(),
            'belum_tap_pulang' => $absensis->whereNull('pulang_attempt_id')->count(),
        ];
    }

    /**
     * Tap terbaru, diterima maupun ditolak.
     *
     * Yang ditolak justru yang paling perlu dilihat: HP asing, di luar radius,
     * dan biometrik gagal adalah jejak percobaan titip absen.
     *
     * @return list<array<string, mixed>>
     */
    private function log(): array
    {
        return array_values(AbsensiAttempt::query()
            ->with(['user:id,name', 'lokasi:id,nama'])
            ->latest()
            ->limit(self::JUMLAH_LOG)
            ->get()
            ->map(fn (AbsensiAttempt $attempt): array => [
                'id' => $attempt->id,
                'nama' => $attempt->user->name,
                'tipe' => $attempt->tipe->value,
                'hasil' => $attempt->hasil->value,
                'diterima' => $attempt->hasil === HasilTap::Diterima,
                'waktu' => $attempt->created_at?->format('d M H:i'),
                'lokasi' => $attempt->lokasi?->nama,
                'jarak_meter' => $attempt->jarak_meter,
                'terverifikasi' => $attempt->terverifikasi,
            ])
            ->all());
    }
}
