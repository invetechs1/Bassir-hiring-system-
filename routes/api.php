<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileCandidateController;
use App\Http\Controllers\Api\MobileDashboardController;
use App\Http\Controllers\Api\MobileJobController;
use App\Http\Controllers\Api\PortalCandidateController;
use App\Http\Controllers\Api\PortalJobController;
use App\Http\Controllers\Api\PortalMeController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('mobile')->middleware('throttle:120,1')->group(function () {
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:12,1');

    Route::middleware('mobile_token')->group(function () {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout']);
        Route::post('/auth/logout-all', [MobileAuthController::class, 'logoutAll']);
        Route::get('/auth/me', [MobileAuthController::class, 'me']);

        Route::get('/dashboard/summary', [MobileDashboardController::class, 'summary']);

        Route::get('/candidates', [MobileCandidateController::class, 'index']);
        Route::get('/candidates/{candidate}', [MobileCandidateController::class, 'show']);
        Route::post('/candidates/{candidate}/status', [MobileCandidateController::class, 'updateStatus'])
            ->middleware('throttle:40,1');

        Route::get('/jobs', [MobileJobController::class, 'index']);
        Route::get('/jobs/{job}', [MobileJobController::class, 'show']);
        Route::post('/jobs/{job}/match', [MobileJobController::class, 'match'])
            ->middleware('throttle:12,1');
    });
});

Route::middleware(['auth', 'throttle:120,1'])->group(function () {
    Route::get('/candidates', [PortalCandidateController::class, 'index']);
    Route::get('/jobs', [PortalJobController::class, 'index']);
    Route::get('/me', [PortalMeController::class, 'show']);
});
