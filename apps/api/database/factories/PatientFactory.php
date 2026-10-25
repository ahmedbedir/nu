<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'full_name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'sex' => fake()->randomElement(['male', 'female']),
            'blood_type' => fake()->randomElement(['A+', 'O+', 'B+', 'AB+']),
            'conditions_state' => 'unknown',
            'medications_state' => 'unknown',
            'allergies_state' => 'unknown',
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}