<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackingShipmentResource;
use App\Models\TrackingShipment;
use App\Tracking\CarrierTrackingAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function show(Request $request, string $booking, CarrierTrackingAvailability $availability): JsonResponse
    {

        $filters = $request->validate([
            'carrier' => ['sometimes', 'string', 'max:10'],
        ]);

        $shipment = TrackingShipment::query()
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
            ->orderByDesc('last_synced_at')
            ->first();

        if (! $shipment) {

            return response()->json([
                'status' => 404,
                'message' => 'No se encontró tracking para el booking indicado.',
                'data' => null,
            ], 404);

        }

        $availability->markAvailable($shipment);

        return response()->json([
            'status' => 200,
            'data' => (new TrackingShipmentResource($shipment))->resolve($request),
        ]);

    }
}
