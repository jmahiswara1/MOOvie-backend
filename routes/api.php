<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MovieController;

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public movie routes (rate limited, ORDER MATTERS - specific before wildcard)
Route::middleware('throttle:300,1')->group(function () {
    Route::get('/movies/search', [MovieController::class, 'search']);
    Route::get('/movies/detail/{id}', [MovieController::class, 'detail']);
    Route::get('/movies/{category}', [MovieController::class, 'getByCategory']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/watchlist', [WatchlistController::class, 'index']);
    Route::get('/watchlist/ids', [WatchlistController::class, 'ids']);
    Route::post('/watchlist/toggle', [WatchlistController::class, 'toggle']);
});
