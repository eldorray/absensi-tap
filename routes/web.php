<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AbsensiPasskeyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\IzinController as AdminIzinController;
use App\Http\Controllers\Admin\JadwalGuruController;
use App\Http\Controllers\Admin\KantorController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\PengumumanController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\RekapHarianController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TahunAjaranController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\JadwalSayaController;
use App\Http\Controllers\PerangkatController;
use App\Http\Controllers\RiwayatAbsensiController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AbsensiController::class, 'index'])->name('dashboard');
    Route::get('jadwal', [JadwalSayaController::class, 'index'])->name('jadwal.index');
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
        Route::get('guru', [GuruController::class, 'index'])->name('guru.index');
        Route::post('guru', [GuruController::class, 'store'])->name('guru.store');
        Route::get('guru/template', [GuruController::class, 'template'])->name('guru.template');
        Route::post('guru/impor', [GuruController::class, 'impor'])->name('guru.impor');
        Route::patch('guru/{guru}', [GuruController::class, 'update'])->name('guru.update');
        Route::patch('perangkat/{perangkat}', [GuruController::class, 'updatePerangkat'])->name('perangkat.update');
    });

require __DIR__.'/settings.php';
