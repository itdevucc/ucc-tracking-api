<?php

namespace App\Console\Commands;

use App\Jobs\SyncTrackingShipment;
use App\Models\TrackingShipment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tracking:dispatch {--limit= : Cantidad máxima}')]
#[Description('Envía a la cola los bookings pendientes de consultar')]
class DispatchTrackingSync extends Command
{
    public function handle(): int
    {

        $limit = (int) ($this->option('limit') ?: config('tracking.sync.batch_size'));

        $shipments = TrackingShipment::query()
            ->where('sync_enabled', true)
            ->where(fn ($query) => $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
            ->orderBy('next_sync_at')
            ->limit($limit)
            ->get();

        $shipments->each(fn ($shipment) => SyncTrackingShipment::dispatch($shipment));

        $this->info("Sincronizaciones enviadas: {$shipments->count()}");

        return self::SUCCESS;
    }
}
