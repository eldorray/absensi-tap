<?php

use App\Http\Controllers\PerangkatController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('perangkat', [PerangkatController::class, 'store'])->name('perangkat.store');
});

require __DIR__.'/settings.php';
