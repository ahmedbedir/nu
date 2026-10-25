<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Versioned API — /api/v1
|--------------------------------------------------------------------------
| Strict authorization on every protected route via auth:sanctum + policies.
*/

Route::prefix('v1')->group(function () {

    // Public ---------------------------------------------------------------
    Route::get('health', HealthController::class);
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('services/{service}', [ServiceController::class, 'show']);

    // Public / pre-auth -----------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('otp/request', [AuthController::class, 'requestOtp']);
        Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
    });

    // Authenticated ----------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::apiResource('patients', PatientController::class);
        Route::post('patients/{patient}/delegations', [PatientController::class, 'grantDelegation']);
        Route::delete('patients/{patient}/delegations/{delegation}', [PatientController::class, 'revokeDelegation']);

        // Health / capabilities (authenticated ping used by apps)
        Route::get('ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));
    });
});