<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'booking_request_id' => BookingRequest::factory(),
            'customer_user_id' => User::factory(),
            'patient_id' => Patient::factory(),
            'visit_address_id' => PatientAddress::factory(),
            'professional_profile_id' => null,
            'state' => 'searching',
            'scheduled_for' => now()->addHours(2),
        ];
    }
}