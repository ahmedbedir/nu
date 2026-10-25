<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Exceptions\OfferNotAvailableException;
use App\Domain\Financials\Services\PricingEngine;
use App\Domain\Matching\Services\CandidateFinder;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\Visit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Application-facing booking orchestration. */
class BookingService
{
    public function __construct(
        private readonly CandidateFinder $finder,
        private readonly DispatchService $dispatch,
        private readonly PricingEngine $pricing,
    ) {}

    public function createRequest(User $customer, Patient $patient, PatientAddress $address, Service $service, array $data): Booking
    {
        if ($service->status !== 'published') {
            throw new \DomainException('الخدمة غير متاحة للحجز');
        }

        $this->assertCoordinates($address);

        return DB::transaction(function () use ($customer, $patient, $address, $service, $data) {
            $request = BookingRequest::create([
                'customer_user_id' => $customer->id,
                'patient_id' => $patient->id,
                'visit_address_id' => $address->id,
                'service_id' => $service->id,
                'required_gender' => $data['required_gender'],
                'gender_consent_for_alternate' => $data['gender_consent_for_alternate'] ?? false,
                'mode' => $data['mode'] ?? 'instant',
                'state' => 'search_pending',
                'form_answers' => $data['form_answers'] ?? null,
                // Recorded for audit only. Never substitutes the visit address.
                'booking_device_lat' => $data['booking_device_lat'] ?? null,
                'booking_device_lng' => $data['booking_device_lng'] ?? null,
                'requested_for' => $data['requested_for'] ?? now()->addHour(),
            ]);

            return Booking::create([
                'booking_request_id' => $request->id,
                'customer_user_id' => $customer->id,
                'patient_id' => $patient->id,
                'visit_address_id' => $address->id,
                'state' => 'search_pending',
                'scheduled_for' => $request->requested_for,
            ]);
        });
    }

    public function search(Booking $booking): array
    {
        $radius = (int) config('homecare.dispatch.initial_radius_km', 10);
        $candidates = $this->finder->find($booking, $radius);

        if ($candidates === []) {
            $booking->update(['state' => 'no_match']);

            return ['booking' => $booking->fresh(), 'wave' => null, 'candidates' => 0];
        }

        $wave = $this->dispatch->openWave($booking, $candidates);

        return [
            'booking' => $booking->fresh(),
            'wave' => $wave->load('offers'),
            'candidates' => count($candidates),
        ];
    }

    public function price(Booking $booking): \App\Models\BookingQuote
    {
        if (! $booking->professional_profile_id) {
            throw new \DomainException('لا يوجد ممارس مقبول بعد');
        }

        return $this->pricing->quote($booking);
    }

    private function assertCoordinates(PatientAddress $address): void
    {
        if (! $address->hasCoordinates() || $address->verification_status !== 'verified') {
            throw new \DomainException('يجب تأكيد عنوان الزيارة وموقعه قبل البحث');
        }
    }
}