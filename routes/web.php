<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard/tracking/{booking}', [TrackingController::class, 'show'])
    ->middleware(['auth', 'verified'])->name('dashboard.tracking.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'can:manage-users'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::delete('/users/{user}/tokens', [UserController::class, 'revokeTokens'])->name('users.tokens.destroy');
});

Route::get('/up', fn () => response()->json(['status' => 'ok']))->middleware('auth');

Route::fallback(fn () => abort(404))->middleware('auth');

require __DIR__.'/auth.php';
