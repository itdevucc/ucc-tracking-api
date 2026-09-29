<?php

use App\Http\Controllers\Api\ApiTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/tokens', [ApiTokenController::class, 'store'])
    ->middleware('throttle:api-token')
    ->name('api.tokens.store');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/auth/tokens', [ApiTokenController::class, 'index']);
    Route::delete('/auth/token', [ApiTokenController::class, 'destroyCurrent']);
    Route::delete('/auth/tokens', [ApiTokenController::class, 'destroyAll']);
});
