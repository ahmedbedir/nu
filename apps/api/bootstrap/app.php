<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Domain\Booking\Exceptions\BookingConflictException;
use App\Domain\Booking\Exceptions\OfferNotAvailableException;
use App\Domain\Clinical\Exceptions\ClinicalBlockedException;
use App\Domain\Shared\Exceptions\DomainRuleException;
use App\Http\Middleware\EnsureStaff;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API requests must never be redirected to a web "login" page.
        // Returning null makes the Authenticate middleware throw, which renders as 401.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('api/*') ? null : '/login'
        );

        // Back-office surfaces require a staff-scoped role, not just a valid token.
        $middleware->alias(['staff' => EnsureStaff::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every /api/* response is JSON: 401 for guests, 422 for validation,
        // 403 for authorization — never an HTML redirect.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ClinicalBlockedException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'clinical_blocked',
                'details' => [
                    'outcome' => $e->outcome,
                    'reasons' => $e->reasons,
                    'missing' => $e->missing,
                    'guidance' => $e->guidance,
                ],
            ], 422);
        });

        $exceptions->render(function (BookingConflictException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->codeName,
            ], 409);
        });

        $exceptions->render(function (OfferNotAvailableException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'العرض لم يعد متاحًا',
                'code' => $e->reason,
            ], 409);
        });

        $exceptions->render(function (DomainRuleException $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->codeName,
            ], $e->status);
        });
    })->create();