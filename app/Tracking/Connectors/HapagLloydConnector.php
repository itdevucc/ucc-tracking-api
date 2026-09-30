<?php

namespace App\Tracking\Connectors;

use App\Contracts\CarrierTrackingConnector;
use App\Data\CarrierTrackingResponse;
use App\Models\TrackingShipment;
use Illuminate\Support\Facades\Http;
use LogicException;

class HapagLloydConnector implements CarrierTrackingConnector
{
    public function key(): string
    {
        return 'hapag-lloyd';
    }

    public function fetch(TrackingShipment $shipment): CarrierTrackingResponse
    {
        $clientId = config('tracking.hapag_lloyd.client_id');

        $clientSecret = config('tracking.hapag_lloyd.client_secret');

        if (! $clientId || ! $clientSecret) {

            throw new LogicException('Faltan HAPAG_CLIENT_ID o HAPAG_CLIENT_SECRET.');

        }

        $query = [
            'carrierBookingReference' => $shipment->booking_reference
        ];

        if ($shipment->last_synced_at) {

            $query['eventCreatedDateTime:gte'] = $shipment->last_synced_at->clone()->subMinutes(5)->toIso8601String();

        }

        $response = Http::acceptJson()
            ->withHeaders([
                'X-IBM-Client-Id' => $clientId,
                'X-IBM-Client-Secret' => $clientSecret,
            ])
            ->retry(3, 500, throw: false)
            ->get(config('tracking.hapag_lloyd.base_url').'/', $query);

        $response->throw();

        return new CarrierTrackingResponse(
            events: $response->status() === 204 ? [] : $response->json(),
            httpStatus: $response->status(),
            apiVersion: $response->header('API-Version') ?? '2.3.3',
        );
    }
}
