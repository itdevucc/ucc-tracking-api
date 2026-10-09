<?php

namespace App\Tracking\Connectors;

use App\Contracts\CarrierTrackingConnector;
use App\Data\CarrierTrackingResponse;
use App\Models\TrackingShipment;
use App\Tracking\MscAccessTokenProvider;
use App\Tracking\MscRequestLimiter;
use App\Tracking\CarrierEventPages;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MscConnector implements CarrierTrackingConnector
{
    public function __construct(private readonly MscAccessTokenProvider $tokens, private readonly MscRequestLimiter $limiter) {}

    public function key(): string
    {
        return 'msc';
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function fetch(TrackingShipment $shipment): CarrierTrackingResponse
    {
        $token = $this->tokens->token();
        $query = [config('tracking.msc.booking_parameter') => $shipment->booking_reference];

        return CarrierEventPages::fetch(fn (?string $cursor) => Http::acceptJson()
            ->withToken($token)
            ->beforeSending(fn () => $this->limiter->acquire())
            ->retry(3, 500, throw: false)
            ->get(config('tracking.msc.base_url').'/events', $cursor === null ? $query : $query + ['cursor' => $cursor]), '2.2');
    }
}
