<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff gate for back-office endpoints.
 *
 * Holding a normal customer token is not enough to reach operations or finance
 * surfaces: the account must carry at least one role whose scope is `staff` or
 * `system`. Individual actions are still checked by permission on each route.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $isStaff = $user->roles()
            ->whereIn('roles.scope', ['staff', 'system'])
            ->where(function ($q) {
                $q->whereNull('user_roles.expires_at')
                    ->orWhere('user_roles.expires_at', '>', now());
            })
            ->exists();

        if (! $isStaff) {
            return response()->json([
                'message' => 'هذا المسار مخصص لموظفي العمليات فقط.',
                'code' => 'staff_only',
            ], 403);
        }

        return $next($request);
    }
}