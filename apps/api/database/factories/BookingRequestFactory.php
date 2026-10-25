<?php

namespace Database\Factories;

use App\Models\BookingRequest;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    protected $model = BookingRequest::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'customer_user_id' => User::factory(),
            'patient_id' => Patient::factory(),
            'visit_address_id' => PatientAddress::factory(),
            'service_id' => Service::factory(),
            'required_gender' => 'female',
            'mode' => 'instant',
            'state' => 'searching',
        ];
    }
}