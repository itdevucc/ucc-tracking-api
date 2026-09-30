<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackingShipmentResource;
use App\Models\TrackingShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function show(Request $request, string $booking): JsonResponse
    {
        $filters = $request->validate([
            'carrier' => ['sometimes', 'string', 'max:10'],
        ]);

        $shipments = TrackingShipment::query()
            ->where('booking_reference', $booking)
            ->when(
                $filters['carrier'] ?? null,
                fn ($query, $carrier) => $query->whereHas(
                    'carrier',
                    fn ($query) => $query->where('scac', strtoupper($carrier)),
                ),
            )
            ->with([
                'carrier',
                'containers.events.location.catalog',
                'containers.events.transportCall.location.catalog',
                'events.location.catalog',
                'events.transportCall.location.catalog',
            ])
            ->get();

        if ($shipments->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'No se encontró tracking para el booking indicado.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => TrackingShipmentResource::collection($shipments)->resolve($request),
        ]);
    }
}
