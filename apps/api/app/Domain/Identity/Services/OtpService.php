<?php

namespace App\Domain\Identity\Services;

use App\Domain\Compliance\Services\AuditLogger;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Configurable phone OTP sign-in (#13, #16, #2).
 *
 * Local/dev exposes the code through the configured channel (log) for testing.
 * In production the code goes through the SMS adapter and is never returned.
 */
class OtpService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{requested: bool, expires_at: string, dev_code: ?string}
     */
    public function request(string $phone, string $purpose = 'login'): array
    {
        $phone = $this->normalisePhone($phone);
        $ttl = (int) config('homecare.otp.ttl_seconds');
        $maxAttempts = (int) config('homecare.otp.max_attempts');

        // HMAC code is not stored in the clear.
        $code = (string) random_int(100000, 999999);

        $otp = PhoneOtp::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'expires_at' => now()->addSeconds($ttl),
            'request_ip' => request()->ip(),
        ]);

        $this->audit->log('auth.otp.requested', null, 'phone', null, $purpose, [
            'purpose' => $purpose,
        ]);

        $channel = app(\App\Domain\Notifications\Contracts\SmsSender::class);
        $channel->send($phone, 'HomeCare Egypt: رمز الدخول الخاص بك هو '.$code, 'otp');

        return [
            'requested' => true,
            'expires_at' => $otp->expires_at->toIso8601String(),
            // LOCAL DEVELOPMENT ONLY — never enabled in production.
            'dev_code' => config('homecare.otp.expose_in_local') ? $code : null,
        ];
    }

    /**
     * Verify a code. On success, create/find the user (phone identity), attach a
     * fresh Sanctum token and consume the OTP. Never leaks whether the phone exists.
     */
    public function verify(string $phone, string $code, string $purpose = 'login'): array
    {
        $phone = $this->normalisePhone($phone);

        $otp = PhoneOtp::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired() || ! $otp->hasAttemptsLeft()) {
            throw new \RuntimeException('رمز الدخول غير صالح أو منتهي الصلاحية');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            if (! $otp->hasAttemptsLeft()) {
                $otp->update(['consumed_at' => now()]); // burn the OTP after exhausting attempts
            }

            throw new \RuntimeException('رمز الدخول غير صحيح');
        }

        $otp->update(['consumed_at' => now()]);

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'مستخدم جديد',
                'phone_country' => '+20',
                'identity_status' => 'unverified',
                'status' => 'active',
            ],
        );

        $this->audit->log('auth.otp.verified', $user, 'user', $user->id, $purpose);

        return [
            'user' => $user,
            'token' => $user->createToken($purpose)->plainTextToken,
            'first_time' => $user->wasRecentlyCreated,
        ];
    }

    /**
     * Purposeful rate limit service that is also reused by controllers.
     *
     * @return bool true when the request may proceed
     */
    public function withinRateLimit(string $phone): bool
    {
        $window = (int) config('homecare.otp.request_window_seconds');
        $limit = (int) config('homecare.otp.request_limit_per_hour');

        $count = PhoneOtp::query()
            ->where('phone', $this->normalisePhone($phone))
            ->where('created_at', '>', now()->subSeconds($window))
            ->count();

        return $count < $limit;
    }

    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return '+20'.substr($phone, 1);
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            return '+20'.substr($phone, 1);
        }

        if (strlen($phone) === 13 && str_starts_with($phone, '+20')) {
            return $phone;
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '20')) {
            return '+'.$phone;
        }

        return $phone;
    }
}