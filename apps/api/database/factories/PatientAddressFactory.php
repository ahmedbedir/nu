<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientAddress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PatientAddress>
 */
class PatientAddressFactory extends Factory
{
    protected $model = PatientAddress::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'patient_id' => Patient::factory(),
            'label' => 'المنزل',
            'address_line' => fake()->streetAddress(),
            // Cairo-ish synthetic coordinates (never real customer addresses).
            'latitude' => 30.0444 + (fake()->randomFloat(4, -0.05, 0.05)),
            'longitude' => 31.2357 + (fake()->randomFloat(4, -0.05, 0.05)),
            'verification_status' => 'verified',
            'verified_at' => now(),
            'is_default' => true,
        ];
    }
}