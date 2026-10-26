<?php

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\AuditLogger;
use App\Domain\Patients\Services\PatientAccess;
use App\Models\Patient;
use App\Models\PatientAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Visit addresses.
 *
 * This is the destination the provider navigates to. It is deliberately separate
 * from any booking device position (#105): a family member may book from one city
 * for a patient in another.
 */
class AddressController extends Controller
{
    public function __construct(
        private readonly PatientAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /api/v1/patients/{patient}/addresses */
    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        return response()->json([
            'data' => $patient->addresses()->orderByDesc('is_default')->get()->map(fn (PatientAddress $a) => $this->present($a)),
        ]);
    }

    /** POST /api/v1/patients/{patient}/addresses */
    public function store(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:48'],
            'address_line' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:64'],
            'floor' => ['nullable', 'string', 'max:32'],
            'apartment' => ['nullable', 'string', 'max:32'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:22,31.9'],
            'longitude' => ['required', 'numeric', 'between:24.6,36.9'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_default' => ['sometimes', 'boolean'],
            'verification_status' => ['sometimes', 'in:unverified,verified'],
        ]);

        $address = DB::transaction(function () use ($data, $patient) {
            if (! empty($data['is_default'])) {
                $patient->addresses()->update(['is_default' => false]);
            }

            return PatientAddress::create([
                'patient_id' => $patient->id,
                'label' => $data['label'] ?? null,
                'address_line' => $data['address_line'],
                'building' => $data['building'] ?? null,
                'floor' => $data['floor'] ?? null,
                'apartment' => $data['apartment'] ?? null,
                'landmark' => $data['landmark'] ?? null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy_m' => $data['accuracy_m'] ?? null,
                'geocode_source' => 'customer_pin',
                // A pin supplied with coordinates is treated as customer-confirmed;
                // higher-risk orders may require an additional check later.
                'verification_status' => $data['verification_status'] ?? 'verified',
                'verified_at' => now(),
                'is_default' => $data['is_default'] ?? $patient->addresses()->count() === 0,
            ]);
        });

        $this->audit->log('patient.address_created', $request->user(), 'patient', $patient->public_id, 'patient_management');

        return response()->json(['address' => $this->present($address)], 201);
    }

    /** DELETE /api/v1/patients/{patient}/addresses/{address} */
    public function destroy(Request $request, Patient $patient, PatientAddress $address): JsonResponse
    {
        $this->authorize('update', $patient);

        if ((int) $address->patient_id !== (int) $patient->id) {
            abort(404);
        }

        $address->delete();

        return response()->json(['deleted' => true]);
    }

    private function present(PatientAddress $address): array
    {
        return [
            'public_id' => $address->public_id,
            'label' => $address->label,
            'address_line' => $address->address_line,
            'building' => $address->building,
            'floor' => $address->floor,
            'apartment' => $address->apartment,
            'landmark' => $address->landmark,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'verification_status' => $address->verification_status,
            'is_default' => (bool) $address->is_default,
        ];
    }
}