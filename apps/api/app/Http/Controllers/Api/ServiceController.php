<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public service catalog — published services only.
 *
 * Guests may browse (decision #14). Nothing here exposes patient data or
 * professional live locations.
 */
class ServiceController extends Controller
{
    /** GET /api/v1/services */
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->where('status', 'published')
            ->orderBy('name_ar')
            ->get()
            ->map(fn (Service $s) => [
                'public_id' => $s->public_id,
                'code' => $s->code,
                'name_ar' => $s->name_ar,
                'name_en' => $s->name_en,
                'description_ar' => $s->description_ar,
                'description_en' => $s->description_en,
                'billing_mode' => $s->billing_mode,
                'default_duration_minutes' => $s->default_duration_minutes,
                'base_price' => $s->base_price,
                'currency' => $s->currency,
                'requires_medical_order' => $s->requires_medical_order,
            ]);

        return response()->json(['data' => $services]);
    }

    /** GET /api/v1/services/{service} */
    public function show(Service $service): JsonResponse
    {
        if ($service->status !== 'published') {
            abort(404);
        }

        return response()->json([
            'service' => [
                'public_id' => $service->public_id,
                'code' => $service->code,
                'name_ar' => $service->name_ar,
                'name_en' => $service->name_en,
                'description_ar' => $service->description_ar,
                'description_en' => $service->description_en,
                'billing_mode' => $service->billing_mode,
                'default_duration_minutes' => $service->default_duration_minutes,
                'base_price' => $service->base_price,
                'currency' => $service->currency,
                'requires_medical_order' => $service->requires_medical_order,
                'allowed_age_range' => $service->allowed_age_range,
                'procedures' => $service->procedures()->get(['code', 'name_ar', 'name_en', 'price', 'billing_mode']),
            ],
        ]);
    }
}