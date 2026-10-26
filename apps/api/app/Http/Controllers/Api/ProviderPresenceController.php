<?php

namespace App\Http\Controllers\Api;

use App\Models\ProfessionalProfile;
use App\Models\ProviderPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Provider online/presence endpoint. Fresh GPS is required for instant offers. */
class ProviderPresenceController extends Controller
{
    /** PUT /api/v1/professionals/presence */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['offline', 'online', 'busy', 'trip', 'visit', 'rest'])],
            'latitude' => ['nullable', 'numeric', 'between:22,31.9'],
            'longitude' => ['nullable', 'numeric', 'between:24.6,36.9'],
        ]);

        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->id)->firstOrFail();

        if ($data['status'] === 'online' && (! isset($data['latitude'], $data['longitude']))) {
            return response()->json([
                'message' => 'يجب إرسال موقع حديث قبل التحول إلى Online',
                'code' => 'fresh_location_required',
            ], 422);
        }

        $presence = ProviderPresence::updateOrCreate(
            ['professional_profile_id' => $profile->id],
            [
                'status' => $data['status'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'captured_at' => isset($data['latitude']) ? now() : null,
            ],
        );

        return response()->json([
            'presence' => [
                'status' => $presence->status,
                'captured_at' => $presence->captured_at?->toIso8601String(),
                'fresh_for_instant' => $presence->hasFreshLocation(),
            ],
        ]);
    }
}