<?php

namespace App\Console\Commands;

use App\Jobs\SyncTrackingShipment;
use App\Tracking\BookingImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tracking:import-bookings {--limit= : Cantidad máxima} {--carrier= : Código SCAC, HLCU o MSCU} {--dispatch : Enviar a la cola}')]
#[Description('Importa bookings elegibles desde la base operacional')]
class ImportTrackingBookings extends Command
{
    public function handle(BookingImporter $importer): int
    {

        $limit = (int) ($this->option('limit') ?: config('tracking.sync.batch_size'));

        $carrierCode = $this->option('carrier');

        if ($carrierCode && ! in_array($carrierCode, config('tracking.source.carrier_codes'), true)) {

            $this->error('La naviera debe ser HLCU o MSCU.');

            return self::INVALID;

        }

        $shipments = $importer->import($limit, $carrierCode);

        if ($this->option('dispatch')) {

            $shipments->each(fn ($shipment) => SyncTrackingShipment::dispatch($shipment));

        }

        $this->info("Bookings importados o actualizados: {$shipments->count()}");

        return self::SUCCESS;

    }
}
