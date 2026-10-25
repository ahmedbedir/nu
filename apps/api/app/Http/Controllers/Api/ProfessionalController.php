<?php

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\AuditLogger;
use App\Domain\Professionals\Services\ProviderEligibility;
use App\Models\ProfessionalCredential;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfessionalController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** POST /api/v1/professionals/onboarding */
    public function onboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profession_code' => ['required', 'string', 'exists:professions,code'],
            'display_name' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'years_experience_self_declared' => ['sometimes', 'integer', 'between:0,60'],
            'declares_external_employment' => ['sometimes', 'boolean'],
        ]);

        $profession = \App\Models\Profession::query()->where('code', $data['profession_code'])->firstOrFail();

        $profile = ProfessionalProfile::firstOrCreate(
            ['user_id' => $request->user()->id, 'profession_id' => $profession->id],
            [
                'display_name' => $data['display_name'],
                'bio' => $data['bio'] ?? null,
                'gender' => $data['gender'],
                'years_experience_self_declared' => $data['years_experience_self_declared'] ?? 0,
                'declares_external_employment' => $data['declares_external_employment'] ?? false,
                // Never active on creation: an approved licence is required first.
                'status' => 'pending',
            ],
        );

        $this->audit->log('professional.onboarding_submitted', $request->user(), 'professional_profile', $profile->public_id, 'verification');

        return response()->json(['profile' => $this->profile($profile)], 201);
    }

    /** POST /api/v1/professionals/credentials */
    public function storeCredential(Request $request): JsonResponse
    {
        $data = $request->validate([
            'credential_type' => ['required', Rule::in(['license', 'qualification', 'union_registration', 'noc', 'speciality_cert'])],
            'title' => ['required', 'string', 'max:160'],
            'issuing_body' => ['nullable', 'string', 'max:160'],
            'number' => ['nullable', 'string', 'max:64'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'document_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->id)->firstOrFail();

        $credential = ProfessionalCredential::create([
            'professional_profile_id' => $profile->id,
            'credential_type' => $data['credential_type'],
            'title' => $data['title'],
            'issuing_body' => $data['issuing_body'] ?? null,
            // Stored encrypted at the application layer; never returned in responses.
            'number_ciphertext' => isset($data['number']) ? $this->encrypt($data['number']) : null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'document_ref' => $data['document_ref'] ?? null,
            'status' => 'pending',
        ]);

        $this->audit->log('professional.credential_submitted', $request->user(), 'credential', $credential->public_id, 'verification', [
            'type' => $credential->credential_type,
        ]);

        return response()->json(['credential' => [
            'public_id' => $credential->public_id,
            'credential_type' => $credential->credential_type,
            'title' => $credential->title,
            'status' => $credential->status,
            'expires_at' => $credential->expires_at?->toDateString(),
        ]], 201);
    }

    /**
     * POST /api/v1/professional-credentials/{credential}/review
     * Requires professionals.review. Approving a licence never auto-approves a
     * service: that is a separate `provider_service_approvals` decision.
     */
    public function reviewCredential(Request $request, ProfessionalCredential $credential): JsonResponse
    {
        if (! $request->user()->hasPermission('professionals.review')) {
            abort(403);
        }

        $data = $request->validate([
            'verdict' => ['required', Rule::in(['approved', 'rejected', 'flagged'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $credential->update([
            'status' => match ($data['verdict']) {
                'approved' => 'verified',
                'rejected' => 'rejected',
                default => 'pending',
            },
            'verification_notes' => $data['notes'] ?? null,
        ]);

        $credential->reviews()->create([
            'reviewer_user_id' => $request->user()->id,
            'verdict' => $data['verdict'],
            'notes' => $data['notes'] ?? null,
        ]);

        // A profile becomes active only once at least one credential is verified.
        $profile = $credential->profile;

        if ($credential->status === 'verified' && $profile->status === 'pending') {
            $profile->update(['status' => 'active']);
        }

        $this->audit->log('professional.credential_reviewed', $request->user(), 'credential', $credential->public_id, 'verification', [
            'verdict' => $data['verdict'],
        ]);

        return response()->json(['credential' => [
            'public_id' => $credential->public_id,
            'status' => $credential->status,
            'profile_status' => $profile->fresh()->status,
        ]]);
    }

    /** POST /api/v1/provider-service-approvals — explicit per-service permission. */
    public function approveService(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('professionals.review')) {
            abort(403);
        }

        $data = $request->validate([
            'profile_public_id' => ['required', 'string'],
            'service_code' => ['required', 'string', 'exists:services,code'],
            'status' => ['required', Rule::in(['approved', 'denied', 'suspended'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = ProfessionalProfile::query()->where('public_id', $data['profile_public_id'])->firstOrFail();
        $service = Service::query()->where('code', $data['service_code'])->firstOrFail();

        if ((int) $service->profession_id !== (int) $profile->profession_id) {
            return response()->json([
                'message' => 'تخصص الخدمة لا يطابق تخصص الممارس',
                'code' => 'profession_mismatch',
            ], 422);
        }

        $approval = $profile->serviceApprovals()->updateOrCreate(
            ['service_code' => $data['service_code']],
            [
                'status' => $data['status'],
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes' => $data['notes'] ?? null,
            ],
        );

        $this->audit->log('provider.service_approval', $request->user(), 'professional_profile', $profile->public_id, 'verification', [
            'service_code' => $data['service_code'],
            'status' => $data['status'],
        ]);

        return response()->json(['approval' => [
            'service_code' => $approval->service_code,
            'status' => $approval->status,
        ]]);
    }

    /** GET /api/v1/professionals/me */
    public function me(Request $request): JsonResponse
    {
        $profile = ProfessionalProfile::query()
            ->where('user_id', $request->user()->id)
            ->with(['credentials', 'serviceApprovals', 'profession'])
            ->first();

        if (! $profile) {
            return response()->json(['profile' => null]);
        }

        return response()->json([
            'profile' => $this->profile($profile) + [
                'credentials' => $profile->credentials->map(fn ($c) => [
                    'public_id' => $c->public_id,
                    'credential_type' => $c->credential_type,
                    'title' => $c->title,
                    'status' => $c->status,
                    'expires_at' => $c->expires_at?->toDateString(),
                ]),
                'service_approvals' => $profile->serviceApprovals->map(fn ($a) => [
                    'service_code' => $a->service_code,
                    'status' => $a->status,
                ]),
            ],
        ]);
    }

    private function profile(ProfessionalProfile $profile): array
    {
        return [
            'public_id' => $profile->public_id,
            'display_name' => $profile->display_name,
            'status' => $profile->status,
            'profession_code' => $profile->profession?->code,
            'years_experience_self_declared' => $profile->years_experience_self_declared,
            'experience_label' => 'self_declared_not_verified',
        ];
    }

    private function encrypt(string $value): string
    {
        try {
            return \Illuminate\Support\Facades\Crypt::encryptString($value);
        } catch (\Throwable) {
            return hash('sha256', $value);
        }
    }
}