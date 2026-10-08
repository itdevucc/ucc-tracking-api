<?php

namespace App\Tracking;

use App\Models\Carrier;
use App\Models\TrackingShipment;
use Illuminate\Support\Collection;

class BookingImporter
{
    public function __construct(private readonly OperationalBookingRepository $source)
    {
    }

    /** @return Collection<int, TrackingShipment> */
    public function import(int $limit, ?string $carrierCode = null): Collection
    {
        $carriers = Carrier::query()->where('is_active', true)->get()->keyBy('scac');

        $sourceSystem = config('database.connections.' . config('tracking.source.connection') . '.database');

        $sourceTable = config('tracking.source.table');

        $now = now();

        $rows = $this->source->candidates($limit, $carrierCode)
            ->map(function (object $booking) use ($carriers, $sourceSystem, $sourceTable, $now) {

                $carrier = $carriers->get($booking->carrier_code);

                if (!$carrier) {

                    return null;

                }

                $podCode = strtoupper(preg_replace('/\s+/', '', (string) ($booking->pod_code ?? '')));

                return [
                    'carrier_id' => $carrier->id,
                    'booking_reference' => trim((string)$booking->booking_reference),
                    'transport_document_reference' => $booking->transport_document_reference ?: null,
                    'source_system' => $sourceSystem,
                    'source_table' => $sourceTable,
                    'source_id' => (string)$booking->source_id,
                    'pod_code' => preg_match('/^[A-Z]{2}[A-Z0-9]{3}$/', $podCode) ? $podCode : null,
                    'pod_name' => $booking->pod_name ?? null,
                    'expected_container_count' => max(0, (int) ($booking->expected_container_count ?? 0)) ?: null,
                    'next_sync_at' => $now,
                    'sync_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

            })
            ->filter()
            ->keyBy(fn(array $row) => $row['carrier_id'] . '|' . $row['booking_reference'])
            ->values();

        if ($rows->isEmpty()) {

            return collect();

        }

        TrackingShipment::query()->upsert(
            $rows->all(),
            ['carrier_id', 'booking_reference'],
            ['transport_document_reference', 'source_system', 'source_table', 'source_id', 'pod_code', 'pod_name', 'expected_container_count', 'updated_at'],
        );

        $shipments = TrackingShipment::query()
            ->where('source_system', $sourceSystem)
            ->where('source_table', $sourceTable)
            ->whereIn('source_id', $rows->pluck('source_id'))
            ->get();

        $shipments->each(fn ($shipment) => $shipment->stopSyncIfDestinationReached());

        return $shipments;

    }
}
