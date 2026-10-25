<?php

namespace App\Http\Controllers\Api;

use App\Domain\Clinical\Services\CatalogPublisher;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogPublisher $publisher) {}

    /** POST /api/v1/admin/catalog/services/{service}/publish */
    public function publish(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate(['change_note' => ['nullable', 'string', 'max:500']]);
        $published = $this->publisher->publish($service, $request->user(), $data['change_note'] ?? null);

        return response()->json([
            'service' => [
                'public_id' => $published->public_id,
                'code' => $published->code,
                'status' => $published->status,
                'published_at' => $published->published_at?->toIso8601String(),
            ],
        ]);
    }

    /** POST /api/v1/admin/catalog/services/{service}/suspend */
    public function suspend(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $suspended = $this->publisher->suspend($service, $request->user(), $data['reason']);

        return response()->json(['service' => [
            'public_id' => $suspended->public_id,
            'status' => $suspended->status,
        ]]);
    }
}