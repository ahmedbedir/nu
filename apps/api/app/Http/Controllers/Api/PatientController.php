<?php

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\AuditLogger;
use App\Domain\Patients\Services\PatientAccess;
use App\Models\Patient;
use App\Models\PatientDelegation;
use App\Models\PatientEmergencyContact;
use App\Models\PatientUserLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /api/v1/patients — only patients this account may see. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $patients = Patient::query()
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('links', fn ($l) => $l->where('user_id', $user->id)->where('verification_status', 'verified'))
                    ->orWhereHas('delegations', fn ($d) => $d->where('grantee_user_id', $user->id)->where('status', 'active')->whereNull('revoked_at'));
            })
            ->withCount(['addresses', 'emergencyContacts'])
            ->latest()
            ->paginate(20);

        return response()->json($patients);
    }

    /** POST /api/v1/patients */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'sex' => ['required', Rule::in(['male', 'female', 'unspecified'])],
            'relationship' => ['required', Rule::in(['self', 'parent', 'guardian', 'relative', 'caregiver'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'blood_type' => ['nullable', 'string', 'max:8'],
            'conditions_state' => ['sometimes', Rule::in(['known', 'unknown'])],
            'medications_state' => ['sometimes', Rule::in(['known', 'unknown'])],
            'allergies_state' => ['sometimes', Rule::in(['known', 'unknown'])],
        ]);

        $user = $request->user();

        $patient = DB::transaction(function () use ($data, $user) {
            $patient = Patient::create([
                'full_name' => $data['full_name'],
                'birth_date' => $data['birth_date'] ?? null,
                'sex' => $data['sex'],
                'phone' => $data['phone'] ?? null,
                'blood_type' => $data['blood_type'] ?? null,
                'conditions_state' => $data['conditions_state'] ?? 'unknown',
                'medications_state' => $data['medications_state'] ?? 'unknown',
                'allergies_state' => $data['allergies_state'] ?? 'unknown',
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            // 'self' is verified immediately; other relationships start pending and
            // require verified authority (decision #18–#20).
            PatientUserLink::create([
                'patient_id' => $patient->id,
                'user_id' => $user->id,
                'relationship' => 'self',
                'verification_status' => 'verified',
                'verified_at' => now(),
                'is_primary' => true,
            ]);

            return $patient;
        });

        $this->audit->log('patient.created', $user, 'patient', $patient->public_id, 'patient_management');

        return response()->json(['patient' => $this->summary($patient)], 201);
    }

    /** GET /api/v1/patients/{patient} */
    public function show(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        $this->audit->log('patient.viewed', $request->user(), 'patient', $patient->public_id, 'patient_management');

        $payload = $this->summary($patient) + [
            'emergency_contacts' => $patient->emergencyContacts()->get(['id', 'name', 'relationship', 'phone', 'priority']),
            'addresses' => $patient->addresses()->get(['public_id', 'label', 'address_line', 'building', 'floor', 'apartment', 'landmark', 'latitude', 'longitude', 'verification_status', 'is_default']),
        ];

        // Clinical detail is only included when the caller has medical scope (T-004).
        if ($this->access->canViewMedical($request->user(), $patient)) {
            $payload['clinical'] = $this->clinical($patient);
        }

        return response()->json(['patient' => $payload]);
    }

    /** PATCH /api/v1/patients/{patient} */
    public function update(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:160'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'sex' => ['sometimes', Rule::in(['male', 'female', 'unspecified'])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:8'],
            'conditions_state' => ['sometimes', Rule::in(['known', 'unknown'])],
            'medications_state' => ['sometimes', Rule::in(['known', 'unknown'])],
            'allergies_state' => ['sometimes', Rule::in(['known', 'unknown'])],
        ]);

        $patient->fill($data)->save();

        $this->audit->log('patient.updated', $request->user(), 'patient', $patient->public_id, 'patient_management', [
            'fields' => array_keys($data),
        ]);

        return response()->json(['patient' => $this->summary($patient->fresh())]);
    }

    /** DELETE /api/v1/patients/{patient} — archive, never a hard delete. */
    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('delete', $patient);

        $patient->update(['status' => 'archived']);

        $this->audit->log('patient.archived', $request->user(), 'patient', $patient->public_id, 'patient_management');

        return response()->json(['archived' => true]);
    }

    /** POST /api/v1/patients/{patient}/delegations */
    public function grantDelegation(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('manageDelegations', $patient);

        $data = $request->validate([
            'grantee_phone' => ['required', 'string', 'max:20'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(PatientDelegation::SCOPES)],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'consent_evidence' => ['nullable', 'string', 'max:255'],
        ]);

        $grantee = User::query()->where('phone', $data['grantee_phone'])->first();

        if (! $grantee) {
            // Do not reveal anything about the record; the grantee must have an account.
            return response()->json([
                'message' => 'لا يمكن منح التفويض لرقم غير مسجل.',
                'code' => 'grantee_not_found',
            ], 422);
        }

        $delegation = PatientDelegation::updateOrCreate(
            ['patient_id' => $patient->id, 'grantee_user_id' => $grantee->id],
            [
                'granted_by_user_id' => $request->user()->id,
                'scopes' => array_values(array_unique($data['scopes'])),
                'status' => 'active',
                'valid_from' => now(),
                'valid_until' => $data['valid_until'] ?? null,
                'consent_evidence' => $data['consent_evidence'] ?? null,
                'revoked_at' => null,
                'revoked_by' => null,
            ],
        );

        $this->audit->log('patient.delegation.granted', $request->user(), 'patient', $patient->public_id, 'delegation_management', [
            'scopes' => $delegation->scopes,
        ]);

        return response()->json(['delegation' => [
            'public_id' => $delegation->public_id,
            'scopes' => $delegation->scopes,
            'status' => $delegation->status,
            'valid_until' => $delegation->valid_until?->toIso8601String(),
        ]], 201);
    }

    /** DELETE /api/v1/patients/{patient}/delegations/{delegation} */
    public function revokeDelegation(Request $request, Patient $patient, PatientDelegation $delegation): JsonResponse
    {
        $this->authorize('manageDelegations', $patient);

        if ((int) $delegation->patient_id !== (int) $patient->id) {
            abort(404); // never confirm cross-patient existence
        }

        $delegation->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        $this->audit->log('patient.delegation.revoked', $request->user(), 'patient', $patient->public_id, 'delegation_management');

        return response()->json(['revoked' => true]);
    }

    private function summary(Patient $patient): array
    {
        return [
            'public_id' => $patient->public_id,
            'full_name' => $patient->full_name,
            'birth_date' => $patient->birth_date?->toDateString(),
            'age' => $patient->age(),
            'sex' => $patient->sex,
            'blood_type' => $patient->blood_type,
            'conditions_state' => $patient->conditions_state,
            'medications_state' => $patient->medications_state,
            'allergies_state' => $patient->allergies_state,
            'status' => $patient->status,
        ];
    }

    private function clinical(Patient $patient): array
    {
        return [
            'conditions' => $patient->conditions()->get(['id', 'name', 'code', 'status']),
            'medications' => $patient->medications()->get(['id', 'name', 'dose', 'frequency', 'route', 'status']),
            'allergies' => $patient->allergies()->get(['id', 'allergen', 'reaction', 'severity', 'status']),
            'clinical_notes' => $patient->clinical_notes,
        ];
    }
}