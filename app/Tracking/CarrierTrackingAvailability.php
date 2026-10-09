<?php

namespace App\Tracking;

use App\Models\TrackingShipment;
use Throwable;

class CarrierTrackingAvailability
{
    public function __construct(private readonly OperationalBookingRepository $source) {}

    public function markAvailable(TrackingShipment $shipment): void
    {

        try {

            $sourceSystem = config('database.connections.'.config('tracking.source.connection').'.database');

            if ($shipment->source_system !== $sourceSystem
                || $shipment->source_table !== config('tracking.source.table')
                || ! filled($shipment->source_id)
                || ! $shipment->events()->exists()) {

                return;

            }

            $this->source->markCarrierTrackingAvailable($shipment);

        } catch (Throwable $exception) {

            // Un fallo al marcar Sailor no invalida el tracking ya almacenado.
            report($exception);

        }

    }
}
