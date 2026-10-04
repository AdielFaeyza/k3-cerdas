<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ViolationController;
use App\Http\Middleware\VerifyEdgeToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Edge (Python) -> API
    Route::post('violations', [ViolationController::class, 'store'])->middleware(VerifyEdgeToken::class);

    // Dashboard HR/HSE
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('violations', [ViolationController::class, 'index']);
        Route::post('violations/{log}/review', [ViolationController::class, 'review']);
        Route::get('leaderboard', [DashboardController::class, 'leaderboard']);
        Route::get('heatmap', [DashboardController::class, 'heatmap']);
    });

    Route::get('snapshots/{log}', [DashboardController::class, 'snapshot'])
        ->name('snapshots.show')->middleware('signed');
});
