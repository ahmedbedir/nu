<?php

namespace App\Http\Controllers\Api;

use App\Models\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsentController extends Controller
{
    /** POST /api/v1/consents — purpose-specific consent, revocable. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', Rule::in(['tracking', 'clinical_data', 'media', 'marketing', 'data_processing', 'refund_credit'])],
            'version' => ['required', 'string', 'max:16'],
            'granted' => ['required', 'boolean'],
            'source' => ['nullable', 'string', 'max:32'],
        ]);

        $consent = Consent::create([
            'user_id' => $request->user()->id,
            'purpose' => $data['purpose'],
            'version' => $data['version'],
            'granted' => $data['granted'],
            'granted_at' => $data['granted'] ? now() : null,
            'source' => $data['source'] ?? 'api',
            'evidence_ref' => $request->header('X-Correlation-ID'),
        ]);

        return response()->json(['consent' => [
            'purpose' => $consent->purpose,
            'version' => $consent->version,
            'granted' => $consent->granted,
            'granted_at' => $consent->granted_at?->toIso8601String(),
        ]], 201);
    }

    /** POST /api/v1/consents/{consent}/revoke */
    public function revoke(Request $request, Consent $consent): JsonResponse
    {
        if ((int) $consent->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $consent->update(['granted' => false, 'revoked_at' => now()]);

        return response()->json(['revoked' => true]);
    }
}