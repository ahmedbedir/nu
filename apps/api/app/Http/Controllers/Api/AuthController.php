<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Services\OtpService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * POST /api/v1/auth/otp/request
     * Rate limited; no account enumeration (same response for known/unknown phone).
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'purpose' => ['sometimes', Rule::in(['login', 'verify_phone', 'recover', 'step_up'])],
        ]);

        $phone = $data['phone'];
        $purpose = $data['purpose'] ?? 'login';

        $key = 'otp:'.$phone;
        if (RateLimiter::tooManyAttempts($key, (int) config('homecare.otp.request_limit_per_hour'))) {
            throw ValidationException::withMessages([
                'phone' => ['كثرة المحاولات، حاول لاحقًا.'], // generic
            ]);
        }

        $result = $this->otp->request($phone, $purpose);
        RateLimiter::hit($key, (int) config('homecare.otp.request_window_seconds'));

        return response()->json([
            'requested' => true,
            'expires_at' => $result['expires_at'],
            'dev_code' => $result['dev_code'], // local only; null in production
        ], 200);
    }

    /**
     * POST /api/v1/auth/otp/verify
     * Exchanges a valid code for a Sanctum bearer token. Binds the phone identity.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'code' => ['required', 'string', 'digits:6'],
            'purpose' => ['sometimes', Rule::in(['login', 'verify_phone', 'recover', 'step_up'])],
        ]);

        try {
            $result = $this->otp->verify($data['phone'], $data['code'], $data['purpose'] ?? 'login');
        } catch (\RuntimeException $e) {
            // Purposefully generic: never reveal whether a phone exists.
            throw ValidationException::withMessages(['code' => ['رمز الدخول غير صالح']]);
        }

        /** @var User $user */
        $user = $result['user'];

        return response()->json([
            'token' => $result['token'],
            'user' => $user->only(['id', 'public_id', 'name', 'phone', 'locale', 'identity_status', 'status']),
            'first_time' => $result['first_time'],
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout — revoke the current token (multi-device revoke works
     * because each device keeps its own token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['revoked' => true]);
    }

    /** GET /api/v1/auth/me */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'identity_status' => $user->identity_status,
                'status' => $user->status,
            ],
        ]);
    }
}