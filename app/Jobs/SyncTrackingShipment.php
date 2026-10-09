<?php

namespace App\Jobs;

use App\Models\TrackingShipment;
use App\Models\TrackingSyncRun;
use App\Tracking\CarrierConnectorManager;
use App\Tracking\CarrierTrackingAvailability;
use App\Tracking\DcsaEventIngestionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncTrackingShipment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 900;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly TrackingShipment $shipment)
    {

        $this->onQueue('tracking');

    }

    public function uniqueId(): string
    {

        return 'tracking-v2:'.$this->shipment->id;

    }

    public function backoff(): array
    {

        return [60, 300, 900, 3600];

    }

    public function handle(CarrierConnectorManager $connectors, DcsaEventIngestionService $ingestion, CarrierTrackingAvailability $availability): void
    {

        $shipment = $this->shipment->fresh('carrier');

        if (! $shipment || $shipment->stopSyncIfDestinationReached() || ! $shipment->sync_enabled) {

            return;

        }

        $run = TrackingSyncRun::query()->create([
            'carrier_id' => $shipment->carrier_id,
            'tracking_shipment_id' => $shipment->id,
            'status' => 'RUNNING',
            'request_parameters' => [
                'booking_reference' => $shipment->booking_reference
            ],
            'started_at' => now(),
        ]);

        try {

            $response = $connectors->for($shipment->carrier->connector)->fetch($shipment);

            $result = $ingestion->ingest($shipment, $response);

            $availability->markAvailable($shipment);

            $run->update([
                'status' => 'COMPLETED',
                'http_status' => $response->httpStatus,
                'payload_count' => count($response->events),
                'events_created' => $result['created'],
                'events_updated' => $result['updated'],
                'finished_at' => now(),
            ]);

        } catch (Throwable $exception) {

            $failures = $shipment->consecutive_failures + 1;

            $shipment->update([
                'consecutive_failures' => $failures,
                'last_error' => str($exception->getMessage())->limit(2000),
                'next_sync_at' => now()->addMinutes(min(1440, 2 ** $failures)),
                'sync_enabled' => $failures < config('tracking.sync.max_failures'),
            ]);

            $run->update([
                'status' => 'FAILED',
                'error_message' => str($exception->getMessage())->limit(2000),
                'finished_at' => now(),
            ]);

            throw $exception;

        }

    }
}
