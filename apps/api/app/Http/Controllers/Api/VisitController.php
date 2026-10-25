<?php

namespace App\Http\Controllers\Api;

use App\Domain\Visits\Services\VisitSessionService;
use App\Models\ProfessionalProfile;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Visit execution endpoints. Every action is scoped: the provider acts as the
 * assigned professional, the customer acts as the booking owner.
 */
class VisitController extends Controller
{
    public function __construct(private readonly VisitSessionService $visits) {}

    /** POST /api/v1/visits/{visit}/departure-eta */
    public function departureEta(Request $request, Visit $visit): JsonResponse
    {
        $this->assertProvider($request, $visit);

        $data = $request->validate(['minutes' => ['required', 'integer', 'between:0,240']]);

        return response()->json(['visit' => $this->summary($this->visits->setDepartureEta($visit, (int) $data['minutes'], $request->user()))]);
    }

    /** POST /api/v1/visits/{visit}/depart */
    public function depart(Request $request, Visit $visit): JsonResponse
    {
        $this->assertProvider($request, $visit);

        return response()->json(['visit' => $this->summary($this->visits->markDeparted($visit, $request->user()))]);
    }

    /**
     * POST /api/v1/visits/{visit}/arrive
     * Records the claim and any geofence signal. Never starts billing.
     */
    public function arrive(Request $request, Visit $visit): JsonResponse
    {
        $this->assertProvider($request, $visit);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:22,31.9'],
            'longitude' => ['nullable', 'numeric', 'between:24.6,36.9'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updated = $this->visits->claimArrival(
            $visit,
            $request->user(),
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
        );

        return response()->json([
            'visit' => $this->summary($updated),
            'note' => 'geofence_is_a_signal_only_it_never_starts_billing',
        ]);
    }

    /** POST /api/v1/visits/{visit}/confirm-arrival */
    public function confirmArrival(Request $request, Visit $visit): JsonResponse
    {
        $this->assertCustomer($request, $visit);

        return response()->json(['visit' => $this->summary($this->visits->confirmArrival($visit, $request->user()))]);
    }

    /** POST /api/v1/visits/{visit}/complete */
    public function complete(Request $request, Visit $visit): JsonResponse
    {
        $this->assertProvider($request, $visit);

        return response()->json(['visit' => $this->summary($this->visits->complete($visit, $request->user()))]);
    }

    private function assertProvider(Request $request, Visit $visit): void
    {
        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->id)->first();

        if (! $profile || (int) $visit->professional_profile_id !== (int) $profile->id) {
            abort(403);
        }
    }

    private function assertCustomer(Request $request, Visit $visit): void
    {
        $visit->loadMissing('booking');

        if ((int) $visit->booking->customer_user_id !== (int) $request->user()->id) {
            abort(403);
        }
    }

    private function summary(Visit $visit): array
    {
        return [
            'public_id' => $visit->public_id,
            'state' => $visit->state,
            'departed_at' => $visit->departed_at?->toIso8601String(),
            'arrival_claimed_at' => $visit->arrival_claimed_at?->toIso8601String(),
            'arrival_confirmed_at' => $visit->arrival_confirmed_at?->toIso8601String(),
            'session_started_at' => $visit->session_started_at?->toIso8601String(),
            'session_ended_at' => $visit->session_ended_at?->toIso8601String(),
            'billed_minutes' => $visit->billed_minutes,
        ];
    }
}