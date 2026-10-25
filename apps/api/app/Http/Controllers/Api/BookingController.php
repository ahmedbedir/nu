<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\DispatchService;
use App\Domain\Patients\Services\PatientAccess;
use App\Models\Booking;
use App\Models\BookingQuote;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly DispatchService $dispatch,
        private readonly PatientAccess $patients,
    ) {}

    /** POST /api/v1/bookings */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_public_id' => ['required', 'string'],
            'address_public_id' => ['required', 'string'],
            'service_public_id' => ['required', 'string'],
            'required_gender' => ['required', Rule::in(['male', 'female'])],
            'gender_consent_for_alternate' => ['sometimes', 'boolean'],
            'mode' => ['sometimes', Rule::in(['instant', 'advanced', 'choose'])],
            'requested_for' => ['nullable', 'date', 'after:now'],
            'form_answers' => ['nullable', 'array'],
            'booking_device_lat' => ['nullable', 'numeric', 'between:22,31.9'],
            'booking_device_lng' => ['nullable', 'numeric', 'between:24.6,36.9'],
        ]);

        $patient = Patient::query()->where('public_id', $data['patient_public_id'])->firstOrFail();

        if (! $this->patients->canBook($request->user(), $patient)) {
            abort(403);
        }

        $address = PatientAddress::query()
            ->where('public_id', $data['address_public_id'])
            ->where('patient_id', $patient->id)
            ->firstOrFail();
        $service = Service::query()->where('public_id', $data['service_public_id'])->firstOrFail();

        $booking = $this->bookings->createRequest($request->user(), $patient, $address, $service, $data);

        return response()->json(['booking' => $this->summary($booking)], 201);
    }

    /** POST /api/v1/bookings/{booking}/search */
    public function search(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);

        $result = $this->bookings->search($booking);

        return response()->json([
            'booking' => $this->summary($result['booking']),
            'candidates' => $result['candidates'],
            'wave' => $result['wave'] ? [
                'public_id' => $result['wave']->public_id,
                'expires_at' => $result['wave']->expires_at?->toIso8601String(),
                'offers' => $result['wave']->offers->map(fn ($offer) => [
                    'public_id' => $offer->public_id,
                    'expires_at' => $offer->expires_at?->toIso8601String(),
                    'eta_seconds' => $offer->eta_seconds,
                    'distance_m' => $offer->distance_m,
                ]),
            ] : null,
        ]);
    }

    /** POST /api/v1/bookings/{booking}/confirm */
    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);

        $data = $request->validate(['quote_id' => ['required', 'integer']]);
        $confirmed = $this->dispatch->customerConfirm($booking, (int) $data['quote_id']);

        return response()->json(['booking' => $this->summary($confirmed)]);
    }

    /** GET /api/v1/bookings/{booking} */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load(['request.service', 'visitAddress', 'professional']);

        return response()->json(['booking' => $this->summary($booking)]);
    }

    /** POST /api/v1/bookings/{booking}/quote */
    public function quote(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);

        $quote = $this->bookings->price($booking);

        return response()->json(['quote' => [
            'public_id' => $quote->public_id,
            'version' => $quote->version,
            'lines' => $quote->lines,
            'subtotal' => $quote->subtotal,
            'total' => $quote->total,
            'currency' => $quote->currency,
            'expires_at' => $quote->expires_at?->toIso8601String(),
        ]]);
    }

    private function summary(Booking $booking): array
    {
        return [
            'public_id' => $booking->public_id,
            'state' => $booking->state,
            'scheduled_for' => $booking->scheduled_for?->toIso8601String(),
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'professional_profile_id' => $booking->professional_profile_id,
            'visit_address_id' => $booking->visit_address?->public_id,
        ];
    }
}