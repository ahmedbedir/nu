<?php

namespace Database\Factories;

use App\Models\ProfessionalProfile;
use App\Models\Profession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProfessionalProfile>
 */
class ProfessionalProfileFactory extends Factory
{
    protected $model = ProfessionalProfile::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'profession_id' => fn () => Profession::query()->firstOrCreate(
                ['code' => 'nurse'],
                ['name_ar' => 'ممرض', 'name_en' => 'Nurse', 'category' => 'nursing'],
            )->id,
            'display_name' => fake()->name(),
            'years_experience_self_declared' => fake()->numberBetween(1, 20),
            'status' => 'active',
            'gender' => fake()->randomElement(['male', 'female']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}