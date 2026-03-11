<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/watchlist', [WatchlistController::class, 'index']);
    Route::get('/watchlist/ids', [WatchlistController::class, 'ids']);
    Route::post('/watchlist/toggle', [WatchlistController::class, 'toggle']);
});
