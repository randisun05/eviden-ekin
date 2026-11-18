<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::get('/login', fn () => Inertia::render('Auth/Login'))->middleware('guest')->name('login');

// Tidak di grup 'guest': user yang sudah login tapi akses Drive-nya dicabut perlu bisa otorisasi ulang.
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware(['auth', 'drive.connected'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::post('/logout', [GoogleAuthController::class, 'logout'])->middleware('auth')->name('logout');
