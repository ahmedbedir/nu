<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * GET /api/v1/health — real dependency probe, no fabricated status.
     */
    public function __invoke(): JsonResponse
    {
        $db = ['connected' => false, 'database' => null, 'error' => null];

        try {
            $db['database'] = DB::connection()->getDatabaseName();
            DB::select('SELECT 1');
            $db['connected'] = true;
        } catch (\Throwable $e) {
            $db['error'] = $e->getMessage();
        }

        $healthy = $db['connected'];

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
            'checks' => ['database' => $db],
        ], $healthy ? 200 : 503);
    }
}