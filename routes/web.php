<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AbsensiPasskeyController;
use App\Http\Controllers\Admin\IzinController as AdminIzinController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\PerangkatController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AbsensiController::class, 'index'])->name('dashboard');

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
        Route::get('izin', [AdminIzinController::class, 'index'])->name('izin.index');
        Route::patch('izin/{izin}', [AdminIzinController::class, 'update'])->name('izin.update');
    });

require __DIR__.'/settings.php';
