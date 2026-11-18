<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidentController;
use App\Http\Controllers\Public\ViewerController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\RhkController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

// Halaman publik penilai (tanpa login).
Route::middleware(['throttle:viewer', 'viewer.headers'])->group(function () {
    Route::get('/v/{code}', [ViewerController::class, 'show'])->where('code', '[a-z0-9]{8,12}')->name('viewer.show');
    Route::post('/v/{code}/pin', [ViewerController::class, 'unlock'])->where('code', '[a-z0-9]{8,12}')->name('viewer.unlock');
});

Route::get('/login', fn () => Inertia::render('Auth/Login'))->middleware('guest')->name('login');

// Tidak di grup 'guest': user yang sudah login tapi akses Drive-nya dicabut perlu bisa otorisasi ulang.
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware(['auth', 'drive.connected'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('periods', PeriodController::class)->except(['create', 'edit']);
    Route::post('periods/{period}/rhks', [RhkController::class, 'store'])->name('rhks.store');
    Route::resource('rhks', RhkController::class)->only(['show', 'update', 'destroy']);
    Route::post('rhks/{rhk}/evidents', [EvidentController::class, 'store'])->name('evidents.store');
    Route::patch('rhks/{rhk}/share', [ShareController::class, 'updateRhk'])->name('rhks.share');
    Route::post('rhks/{rhk}/share/regenerate', [ShareController::class, 'regenerateRhk'])->name('rhks.share.regenerate');
    Route::patch('evidents/{evident}/share', [ShareController::class, 'updateEvident'])->name('evidents.share');
    Route::post('evidents/{evident}/share/regenerate', [ShareController::class, 'regenerateEvident'])->name('evidents.share.regenerate');
    Route::delete('evidents/{evident}', [EvidentController::class, 'destroy'])->name('evidents.destroy');
});

Route::post('/logout', [GoogleAuthController::class, 'logout'])->middleware('auth')->name('logout');
