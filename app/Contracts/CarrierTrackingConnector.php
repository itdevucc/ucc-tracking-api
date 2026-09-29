<?php

namespace App\Contracts;

use App\Data\CarrierTrackingResponse;
use App\Models\TrackingShipment;

interface CarrierTrackingConnector
{
    public function key(): string;

    public function fetch(TrackingShipment $shipment): CarrierTrackingResponse;
}
