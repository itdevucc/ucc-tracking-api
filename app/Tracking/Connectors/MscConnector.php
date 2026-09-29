<?php

namespace App\Tracking\Connectors;

use App\Contracts\CarrierTrackingConnector;
use App\Data\CarrierTrackingResponse;
use App\Models\TrackingShipment;
use App\Tracking\MscAccessTokenProvider;
use Illuminate\Support\Facades\Http;

class MscConnector implements CarrierTrackingConnector
{
    public function __construct(private readonly MscAccessTokenProvider $tokens) {}

    public function key(): string
    {
        return 'msc';
    }

    public function fetch(TrackingShipment $shipment): CarrierTrackingResponse
    {
        $response = Http::acceptJson()
            ->withToken($this->tokens->token())
            ->retry(3, 500, throw: false)
            ->get(config('tracking.msc.base_url').'/events', [
                config('tracking.msc.booking_parameter') => $shipment->booking_reference,
            ]);

        $response->throw();

        return new CarrierTrackingResponse(
            events: $response->status() === 204 ? [] : $response->json(),
            httpStatus: $response->status(),
            apiVersion: $response->header('API-Version') ?? '2.2',
        );
    }
}
