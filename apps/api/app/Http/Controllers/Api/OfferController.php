<?php

namespace App\Http\Controllers\Api;

use App\Models\DispatchOffer;
use App\Models\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /** GET /api/v1/professionals/offers */
    public function index(Request $request): JsonResponse
    {
        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->id)->firstOrFail();

        $offers = DispatchOffer::query()
            ->where('professional_profile_id', $profile->id)
            ->where('status', 'offered')
            ->where('expires_at', '>', now())
            ->with(['booking.request.service', 'booking.visitAddress'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DispatchOffer $offer) => [
                'public_id' => $offer->public_id,
                'expires_at' => $offer->expires_at?->toIso8601String(),
                'eta_seconds' => $offer->eta_seconds,
                'distance_m' => $offer->distance_m,
                'booking_public_id' => $offer->booking?->public_id,
                'service_name_ar' => $offer->booking?->request?->service?->name_ar,
                'service_name_en' => $offer->booking?->request?->service?->name_en,
                'visit_area' => $offer->booking?->visitAddress?->label,
            ]);

        return response()->json(['data' => $offers]);
    }
}