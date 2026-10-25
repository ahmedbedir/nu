<?php

namespace App\Http\Controllers\Api;

use App\Domain\Safety\Services\SosService;
use App\Models\Booking;
use App\Models\SosIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SosController extends Controller
{
    public function __construct(private readonly SosService $sos) {}

    /**
     * POST /api/v1/sos
     *
     * Highest priority, so intake is durable first and notification fan-out is
     * best-effort with its real result recorded. An incident is never reported as
     * acknowledged before an operator actually acknowledges it.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reporter_role' => ['required', Rule::in(['customer', 'professional'])],
            'booking_public_id' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:22,31.9'],
            'longitude' => ['nullable', 'numeric', 'between:24.6,36.9'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'client_reference' => ['nullable', 'string', 'max:96'],
        ]);

        $booking = isset($data['booking_public_id'])
            ? Booking::query()->where('public_id', $data['booking_public_id'])->first()
            : null;

        if ($booking) {
            $this->authorize('view', $booking);
        }

        $incident = $this->sos->initiate(
            $data['reporter_role'],
            $request->user(),
            $booking?->id,
            null,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
        );

        if (! empty($data['contact_phone'])) {
            try {
                $incident->update(['contact_phone_ciphertext' => \Illuminate\Support\Facades\Crypt::encryptString($data['contact_phone'])]);
            } catch (\Throwable) {
                // Never let encryption failure block an SOS.
            }
        }

        $delivery = $this->sos->dispatchNotifications($incident->fresh());

        return response()->json([
            'incident' => $this->summary($incident->fresh()),
            // Truthful delivery status; a log-only channel reports false.
            'notified' => $delivery['delivered'],
            'sms_delivered' => $delivery['sms_delivered'],
            'channels' => $delivery['attempts'],
            'guidance' => 'not_an_emergency_service_call_112_or_123_directly',
        ], 201);
    }

    /** POST /api/v1/sos/{incident}/ack — requires the sos.ack permission. */
    public function acknowledge(Request $request, SosIncident $incident): JsonResponse
    {
        if (! $request->user()->hasPermission('sos.ack')) {
            abort(403);
        }

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        return response()->json(['incident' => $this->summary($this->sos->acknowledge($incident, $request->user(), 'internal', $data['notes'] ?? null))]);
    }

    /** POST /api/v1/sos/{incident}/close */
    public function close(Request $request, SosIncident $incident): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(['reporter_acknowledged', 'senior_review'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $closer = $request->user();

        if ($data['reason'] === 'reporter_acknowledged') {
            if ((int) $incident->user_id !== (int) $closer->id && ! $closer->hasPermission('sos.close')) {
                abort(403);
            }

            $closed = $this->sos->closeByReporter($incident, $closer);
        } else {
            if (! $closer->hasPermission('sos.close')) {
                abort(403);
            }

            $closed = $this->sos->closeBySeniorReview($incident, $closer, (string) ($data['notes'] ?? ''));
        }

        return response()->json(['incident' => $this->summary($closed)]);
    }

    private function summary(SosIncident $incident): array
    {
        return [
            'public_id' => $incident->public_id,
            'state' => $incident->state,
            'reporter_role' => $incident->reporter_role,
            'acked_at' => $incident->acked_at?->toIso8601String(),
            'closed_at' => $incident->closed_at?->toIso8601String(),
            'closure_reason' => $incident->closure_reason,
            'occurred_at' => $incident->occurred_at?->toIso8601String(),
        ];
    }
}