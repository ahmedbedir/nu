<?php

namespace Tests\Feature;

use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-002 — OTP verification is rate limited; expired/incorrect codes never authenticate.
 */
class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_returns_code_only_in_local_dev(): void
    {
        $res = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01012345678']);

        $res->assertOk()->assertJsonPath('requested', true);
        $this->assertNotNull($res->json('dev_code'), 'dev_code expected in testing env');

        // Stored as a hash, never in the clear.
        $otp = PhoneOtp::query()->latest()->first();
        $this->assertNotSame($res->json('dev_code'), $otp->code_hash);
        $this->assertTrue(Hash::check($res->json('dev_code'), $otp->code_hash));
    }

    public function test_correct_code_authenticates_and_is_single_use(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01012345678'])->json('dev_code');

        $ok = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $code]);
        $ok->assertOk()->assertJsonStructure(['token', 'user' => ['public_id', 'phone']]);

        $this->assertDatabaseHas('users', ['phone' => '+201012345678']);

        // Replay of the same code must fail (consumed).
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $code])
            ->assertStatus(422);
    }

    public function test_incorrect_code_never_authenticates(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01012345678'])->json('dev_code');
        $wrong = $code === '111111' ? '222222' : '111111';

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $wrong])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01012345678'])->json('dev_code');

        PhoneOtp::query()->latest()->first()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01012345678', 'code' => $code])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_otp_attempts_are_capped(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01099999999'])->json('dev_code');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01099999999', 'code' => '000000'])
                ->assertStatus(422);
        }

        // Even the correct code is now dead — attempts exhausted.
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01099999999', 'code' => $code])
            ->assertStatus(422);
    }

    public function test_authenticated_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.public_id', $user->public_id);
    }
}