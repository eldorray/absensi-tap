<?php

use App\Enums\Role;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AbsensiPasskeyController;
use App\Http\Controllers\AbsensiSiswaController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\IzinController as AdminIzinController;
use App\Http\Controllers\Admin\JadwalGuruController;
use App\Http\Controllers\Admin\KantorController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\OrangTuaController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\PengumumanController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\RekapHarianController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Admin\TahunAjaranController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\JadwalSayaController;
use App\Http\Controllers\KelasSayaController;
use App\Http\Controllers\OrangTua\DashboardController as OrangTuaDashboardController;
use App\Http\Controllers\PerangkatController;
use App\Http\Controllers\RiwayatAbsensiController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('aplikasi', function () {
    return match (request()->user()->role) {
        Role::Admin => to_route('admin.dashboard'),
        Role::OrangTua => to_route('orang-tua.dashboard'),
        default => to_route('dashboard'),
    };
})->middleware(['auth', 'verified'])->name('aplikasi');

Route::middleware(['auth', 'verified', 'can:orang-tua'])
    ->prefix('orang-tua')
    ->name('orang-tua.')
    ->group(function () {
        Route::get('/', OrangTuaDashboardController::class)->name('dashboard');
    });

Route::middleware(['auth', 'verified', 'can:pegawai'])->group(function () {
    Route::get('dashboard', [AbsensiController::class, 'index'])->name('dashboard');
    Route::get('jadwal', [JadwalSayaController::class, 'index'])->name('jadwal.index');
    Route::get('kelas-saya', [KelasSayaController::class, 'index'])->name('kelas-saya.index');
    Route::get('absensi-siswa', [AbsensiSiswaController::class, 'index'])->name('absensi-siswa.index');
    // Harus di atas absensi-siswa/{kelas}, atau 'cetak' terbaca sebagai id kelas.
    Route::get('absensi-siswa/cetak', [AbsensiSiswaController::class, 'cetak'])->name('absensi-siswa.cetak');
    Route::get('absensi-siswa/{kelas}', [AbsensiSiswaController::class, 'show'])->name('absensi-siswa.show');

    // Mengisi absensi siswa adalah pekerjaan guru piket, bukan wali kelas:
    // guru hanya membaca lewat route di atas. Lihat gate 'piket'.
    Route::middleware('can:piket')->group(function (): void {
        Route::put('absensi-siswa/{kelas}/draft', [AbsensiSiswaController::class, 'draft'])->name('absensi-siswa.draft');
        Route::put('absensi-siswa/{kelas}/finalisasi', [AbsensiSiswaController::class, 'finalisasi'])->name('absensi-siswa.finalisasi');
        Route::put('absensi-siswa/{kelas}/buka-finalisasi', [AbsensiSiswaController::class, 'bukaFinalisasi'])->name('absensi-siswa.buka-finalisasi');
    });
    Route::get('riwayat', [RiwayatAbsensiController::class, 'index'])->name('riwayat.index');

    Route::post('absensi', [AbsensiController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('absensi.store');

    Route::get('absensi/passkey-options', [AbsensiPasskeyController::class, 'index'])
        ->name('absensi.passkey-options');

    Route::post('absensi/passkey-verify', [AbsensiPasskeyController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('absensi.passkey-verify');

    Route::post('perangkat', [PerangkatController::class, 'store'])->name('perangkat.store');

    Route::get('izin', [IzinController::class, 'index'])->name('izin.index');
    Route::post('izin', [IzinController::class, 'store'])->name('izin.store');
    Route::get('izin/{izin}/lampiran', [IzinController::class, 'lampiran'])
        ->middleware('can:view,izin')
        ->name('izin.lampiran');
});

Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('rekap', [RekapController::class, 'index'])->name('rekap.index');
        Route::get('rekap/export', [RekapController::class, 'export'])->name('rekap.export');
        Route::get('rekap/cetak', [RekapController::class, 'cetak'])->name('rekap.cetak');
        Route::get('rekap-harian', [RekapHarianController::class, 'index'])->name('rekap-harian.index');
        Route::get('rekap-harian/export', [RekapHarianController::class, 'export'])->name('rekap-harian.export');
        Route::delete('rekap-harian/{guru}', [RekapHarianController::class, 'reset'])->name('rekap-harian.reset');
        Route::get('izin', [AdminIzinController::class, 'index'])->name('izin.index');
        Route::patch('izin/{izin}', [AdminIzinController::class, 'update'])->name('izin.update');
        Route::get('pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan.edit');
        Route::post('aplikasi', [PengaturanController::class, 'simpanAplikasi'])->name('aplikasi.update');
        Route::post('lokasi', [PengaturanController::class, 'simpanLokasi'])->name('lokasi.store');
        Route::patch('lokasi/{lokasi}', [PengaturanController::class, 'ubahLokasi'])->name('lokasi.update');
        Route::delete('lokasi/{lokasi}', [PengaturanController::class, 'hapusLokasi'])->name('lokasi.destroy');
        Route::put('jadwal', [PengaturanController::class, 'simpanJadwal'])->name('jadwal.update');
        Route::put('pengaturan-absensi', [PengaturanController::class, 'simpanPengaturanAbsensi'])->name('pengaturan-absensi.update');
        Route::get('jadwal-guru', [JadwalGuruController::class, 'index'])->name('jadwal-guru.index');
        Route::put('jadwal-guru/{guru}', [JadwalGuruController::class, 'update'])->name('jadwal-guru.update');
        Route::delete('jadwal-guru/{guru}', [JadwalGuruController::class, 'destroy'])->name('jadwal-guru.destroy');
        Route::post('hari-libur', [PengaturanController::class, 'simpanHariLibur'])->name('hari-libur.store');
        Route::delete('hari-libur/{hariLibur}', [PengaturanController::class, 'hapusHariLibur'])->name('hari-libur.destroy');
        Route::get('user', [UserController::class, 'index'])->name('user.index');
        Route::post('user', [UserController::class, 'store'])->name('user.store');
        Route::patch('user/{user}', [UserController::class, 'update'])->name('user.update');
        Route::post('user/{user}/reset-password', [UserController::class, 'resetPassword'])->name('user.reset-password');
        Route::delete('user/{user}', [UserController::class, 'destroy'])->name('user.destroy');
        Route::get('orang-tua', [OrangTuaController::class, 'index'])->name('orang-tua.index');
        Route::post('orang-tua', [OrangTuaController::class, 'store'])->name('orang-tua.store');
        Route::patch('orang-tua/{orangTua}', [OrangTuaController::class, 'update'])->name('orang-tua.update');
        Route::post('orang-tua/{orangTua}/reset-password', [OrangTuaController::class, 'resetPassword'])->name('orang-tua.reset-password');
        Route::delete('orang-tua/{orangTua}', [OrangTuaController::class, 'destroy'])->name('orang-tua.destroy');
        Route::get('role', [RoleController::class, 'index'])->name('role.index');
        Route::get('kantor', [KantorController::class, 'index'])->name('kantor.index');
        Route::post('kantor', [KantorController::class, 'store'])->name('kantor.store');
        Route::put('kantor/{kantor}', [KantorController::class, 'update'])->name('kantor.update');
        Route::delete('kantor/{kantor}', [KantorController::class, 'destroy'])->name('kantor.destroy');
        Route::get('tahun-ajaran', [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
        Route::post('tahun-ajaran', [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
        Route::put('tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update'])->name('tahun-ajaran.update');
        Route::post('tahun-ajaran/{tahunAjaran}/aktifkan', [TahunAjaranController::class, 'aktifkan'])->name('tahun-ajaran.aktifkan');
        Route::post('tahun-ajaran/{tahunAjaran}/lihat', [TahunAjaranController::class, 'lihat'])->name('tahun-ajaran.lihat');
        Route::get('pengumuman', [PengumumanController::class, 'index'])->name('pengumuman.index');
        Route::post('pengumuman', [PengumumanController::class, 'store'])->name('pengumuman.store');
        Route::put('pengumuman/{pengumuman}', [PengumumanController::class, 'update'])->name('pengumuman.update');
        Route::delete('pengumuman/{pengumuman}', [PengumumanController::class, 'destroy'])->name('pengumuman.destroy');
        Route::resource('siswa', SiswaController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('siswa/template', [SiswaController::class, 'template'])->name('siswa.template');
        Route::post('siswa/impor', [SiswaController::class, 'impor'])->name('siswa.impor');
        Route::put('siswa/{siswa}/orang-tua', [SiswaController::class, 'sinkronkanOrangTua'])->name('siswa.orang-tua.update');
        Route::resource('kelas', KelasController::class)->parameters(['kelas' => 'kelas'])->only(['index', 'store', 'update', 'destroy']);
        Route::post('kelas/{kelas}/anggota', [KelasController::class, 'tempatkan'])->name('kelas.anggota.store');
        Route::delete('kelas/{kelas}/anggota/{anggota}', [KelasController::class, 'keluarkan'])->name('kelas.anggota.destroy');
        Route::post('kelas/{kelas}/pengganti', [KelasController::class, 'tugaskanPengganti'])->name('kelas.pengganti.store');
        Route::delete('kelas/{kelas}/pengganti/{pengganti}', [KelasController::class, 'cabutPengganti'])->name('kelas.pengganti.destroy');
        Route::get('guru', [GuruController::class, 'index'])->name('guru.index');
        Route::post('guru', [GuruController::class, 'store'])->name('guru.store');
        Route::get('guru/template', [GuruController::class, 'template'])->name('guru.template');
        Route::post('guru/impor', [GuruController::class, 'impor'])->name('guru.impor');
        Route::patch('guru/{guru}', [GuruController::class, 'update'])->name('guru.update');
        Route::patch('perangkat/{perangkat}', [GuruController::class, 'updatePerangkat'])->name('perangkat.update');
    });

require __DIR__.'/settings.php';
