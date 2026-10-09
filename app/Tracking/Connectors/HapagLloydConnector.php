<?php

namespace App\Tracking\Connectors;

use App\Contracts\CarrierTrackingConnector;
use App\Data\CarrierTrackingResponse;
use App\Models\TrackingShipment;
use App\Tracking\CarrierEventPages;
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

        return CarrierEventPages::fetch(fn (?string $cursor) => Http::acceptJson()
            ->withHeaders([
                'X-IBM-Client-Id' => $clientId,
                'X-IBM-Client-Secret' => $clientSecret,
            ])
            ->retry(3, 500, throw: false)
            ->get(config('tracking.hapag_lloyd.base_url').'/', $cursor === null ? $query : $query + ['cursor' => $cursor]), '2.3.3');

    }
}
