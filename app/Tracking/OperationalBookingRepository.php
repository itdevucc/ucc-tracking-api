<?php

namespace App\Tracking;

use App\Models\TrackingShipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use LogicException;

class OperationalBookingRepository
{
    public function markCarrierTrackingAvailable(TrackingShipment $shipment): void
    {

        $connection = config('tracking.source.connection');

        $bookings = $this->safeTable(config('tracking.source.table'));

        $carriers = $this->safeTable(config('tracking.source.carriers_table'));

        $this->assertSourceDatabaseIsAllowed($connection);

        DB::connection($connection)
            ->table($bookings)
            ->where('id', $shipment->source_id)
            ->where('carrier_booking_number', $shipment->booking_reference)
            ->whereIn('id_naviera_salida', function ($query) use ($carriers, $shipment) {

                $query->select('id')->from($carriers)->where('code', $shipment->carrier->scac);

            })
            ->where(function ($query) {

                $query->where('has_carrier_tracking', 0)->orWhereNull('has_carrier_tracking');

            })
            ->update(['has_carrier_tracking' => 1]);

    }

    /**
     * @param int $limit
     * @return Collection
     */
    public function candidates(int $limit, ?string $carrierCode = null): Collection
    {

        return $this->candidateQuery($carrierCode)
            ->limit($limit)
            ->get();

    }

    /**
     * @return int
     */
    public function countCandidates(): int
    {

        return $this->candidateQuery()->count('booking.id');

    }

    /**
     * @return Collection
     */
    public function countCandidatesByState(): Collection
    {

        return $this->candidateQuery()
            ->reorder()
            ->select([
                'state.id as state_id',
                'state.name as state_name',
                'state.code as state_code',
                DB::raw('COUNT(booking.id) as total'),
            ])
            ->groupBy('state.id', 'state.name', 'state.code')
            ->orderBy('state.id')
            ->get();

    }

    /**
     * @return Builder
     */
    private function candidateQuery(?string $carrierCode = null): Builder
    {

        $connection = config('tracking.source.connection');

        $bookings = $this->safeTable(config('tracking.source.table'));

        $carriers = $this->safeTable(config('tracking.source.carriers_table'));

        $states = $this->safeTable(config('tracking.source.states_table'));

        $bookingHeaders = $this->safeTable(config('tracking.source.bookings_table'));

        $ports = $this->safeTable(config('tracking.source.ports_table'));

        $this->assertSourceDatabaseIsAllowed($connection);

        $query = DB::connection($connection)
            ->table("{$bookings} as booking")
            ->join("{$carriers} as carrier", 'carrier.id', '=', 'booking.id_naviera_salida')
            ->join("{$states} as state", 'state.id', '=', 'booking.booking_itinerary_state_id')
            ->leftJoin("{$bookingHeaders} as header", 'header.id', '=', 'booking.booking_id')
            ->leftJoin("{$ports} as pod", 'pod.id', '=', 'header.port_discharge_id')
            ->whereIn('carrier.code', config('tracking.source.carrier_codes'))
            ->whereIn('state.code', config('tracking.source.state_codes'))

            // Filtro temporal para las pruebas actuales.
            ->whereBetween('booking.pol_eta', [now()->subMonth(), now()])

            ->whereNotNull('booking.carrier_booking_number')
            ->where('booking.carrier_booking_number', '<>', '')
            ->select([
                'booking.id as source_id',
                'booking.carrier_booking_number as booking_reference',
                'booking.bill_lading as transport_document_reference',
                'booking.pol_eta as source_date',
                'pod.code as pod_code',
                'pod.name as pod_name',
                'booking.cant as expected_container_count',
                'carrier.code as carrier_code',
                'carrier.name as carrier_name',
                'state.code as booking_state_code',
                'state.name as booking_state_name',
            ])
            ->orderBy('booking.id');

        if ($carrierCode) {

            $query->where('carrier.code', $carrierCode);

        }

        return $query;

    }

    /**
     * @param string $connection
     * @return void
     */
    private function assertSourceDatabaseIsAllowed(string $connection): void
    {

        $sourceDatabase = DB::connection($connection)->getDatabaseName();

        $targetDatabase = DB::connection(config('database.default'))->getDatabaseName();

        $allowedDatabases = config('tracking.source.allowed_databases', []);

        if ($sourceDatabase === $targetDatabase) {

            throw new LogicException("La conexión de bookings no puede apuntar a la base de destino [{$targetDatabase}].");

        }

        if (!in_array($sourceDatabase, $allowedDatabases, true)) {

            throw new LogicException("La base de bookings [{$sourceDatabase}] no está autorizada para este entorno.");

        }

    }

    /**
     * @param string $table
     * @return string
     */
    private function safeTable(string $table): string
    {

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {

            throw new InvalidArgumentException('El nombre de tabla de origen no es válido.');

        }

        return $table;

    }

}
