<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ProviderOfferController;
use App\Http\Controllers\Api\ProviderPresenceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SosController;
use App\Http\Controllers\Api\VisitController;
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

        // Visit addresses (the destination, never the booker's device position)
        Route::get('patients/{patient}/addresses', [AddressController::class, 'index']);
        Route::post('patients/{patient}/addresses', [AddressController::class, 'store']);
        Route::delete('patients/{patient}/addresses/{address}', [AddressController::class, 'destroy']);

        // Provider presence and incoming offers
        Route::put('professionals/presence', [ProviderPresenceController::class, 'update']);
        Route::get('professionals/offers', [OfferController::class, 'index']);

        // Booking and dispatch
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::post('bookings/{booking}/search', [BookingController::class, 'search']);
        Route::post('bookings/{booking}/quote', [BookingController::class, 'quote']);
        Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::post('offers/{offer}/accept', [ProviderOfferController::class, 'accept']);
        Route::post('offers/{offer}/decline', [ProviderOfferController::class, 'decline']);

        // Visits: arrival claim and customer confirmation are intentionally separate.
        Route::post('visits/{visit}/departure-eta', [VisitController::class, 'departureEta']);
        Route::post('visits/{visit}/depart', [VisitController::class, 'depart']);
        Route::post('visits/{visit}/arrive', [VisitController::class, 'arrive']);
        Route::post('visits/{visit}/confirm-arrival', [VisitController::class, 'confirmArrival']);
        Route::post('visits/{visit}/complete', [VisitController::class, 'complete']);

        // SOS
        Route::post('sos', [SosController::class, 'store']);
        Route::post('sos/{incident}/ack', [SosController::class, 'acknowledge']);
        Route::post('sos/{incident}/close', [SosController::class, 'close']);

        // Professional onboarding and verification
        Route::post('professionals/onboarding', [ProfessionalController::class, 'onboard']);
        Route::get('professionals/me', [ProfessionalController::class, 'me']);
        Route::post('professionals/credentials', [ProfessionalController::class, 'storeCredential']);
        Route::post('professional-credentials/{credential}/review', [ProfessionalController::class, 'reviewCredential']);
        Route::post('provider-service-approvals', [ProfessionalController::class, 'approveService']);

        // Consent (purpose-specific, revocable)
        Route::post('consents', [ConsentController::class, 'store']);
        Route::post('consents/{consent}/revoke', [ConsentController::class, 'revoke']);

        // Finance: cash attestation, refunds, disputes
        Route::post('invoices/{invoice}/cash-attestation', [FinanceController::class, 'cashAttestation']);
        Route::post('invoices/{invoice}/disputes', [FinanceController::class, 'openDispute']);
        Route::post('refund-requests', [FinanceController::class, 'requestRefund']);

        // Back office — staff-scoped roles only
        Route::middleware('staff')->prefix('admin')->group(function () {
            Route::post('catalog/services/{service}/publish', [CatalogController::class, 'publish']);
            Route::post('catalog/services/{service}/suspend', [CatalogController::class, 'suspend']);
            Route::post('finance/refunds/{refund}/approve', [FinanceController::class, 'approveRefund']);
            Route::get('operations/stream', [OperationsController::class, 'stream']);
        });

        Route::post('patients/{patient}/delegations', [PatientController::class, 'grantDelegation']);
        Route::delete('patients/{patient}/delegations/{delegation}', [PatientController::class, 'revokeDelegation']);

        // Health / capabilities (authenticated ping used by apps)
        Route::get('ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));
    });
});