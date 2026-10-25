<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected static int $phoneSeq = 0;

    public function definition(): array
    {
        // Synthetic phone numbers only — never real customer data (test policy).
        static::$phoneSeq++;

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => '+2010'.str_pad((string) (10000000 + static::$phoneSeq), 8, '0', STR_PAD_LEFT),
            'phone_country' => '+20',
            'locale' => 'ar',
            'status' => 'active',
            'identity_status' => 'unverified',
            'gender' => 'unspecified',
            'remember_token' => Str::random(10),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'identity_status' => 'verified',
            'identity_verified_at' => now(),
        ]);
    }

    public function withPhone(string $phone): static
    {
        return $this->state(fn () => ['phone' => $phone]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}