<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\Exceptions\OfferNotAvailableException;
use App\Domain\Booking\Services\DispatchService;
use App\Models\DispatchOffer;
use App\Models\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderOfferController extends Controller
{
    public function __construct(private readonly DispatchService $dispatch) {}

    /** POST /api/v1/offers/{offer}/accept */
    public function accept(Request $request, DispatchOffer $offer): JsonResponse
    {
        $profile = ProfessionalProfile::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $result = $this->dispatch->acceptOffer($offer, $profile);
        } catch (OfferNotAvailableException $e) {
            return response()->json([
                'message' => 'العرض لم يعد متاحًا',
                'code' => $e->reason,
            ], 409);
        }

        return response()->json([
            'booking' => [
                'public_id' => $result['booking']->public_id,
                'state' => $result['booking']->state,
            ],
            'hold' => [
                'expires_at' => $result['hold']->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /** POST /api/v1/offers/{offer}/decline */
    public function decline(Request $request, DispatchOffer $offer): JsonResponse
    {
        $profile = ProfessionalProfile::query()->where('user_id', $request->user()->id)->firstOrFail();

        if ((int) $offer->professional_profile_id !== (int) $profile->id || $offer->status !== 'offered') {
            return response()->json(['message' => 'العرض لم يعد متاحًا', 'code' => 'offer_not_available'], 409);
        }

        $offer->update(['status' => 'declined', 'responded_at' => now()]);

        return response()->json(['declined' => true]);
    }
}