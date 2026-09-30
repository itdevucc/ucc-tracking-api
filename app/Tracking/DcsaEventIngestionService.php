<?php

namespace App\Tracking;

use App\Data\CarrierTrackingResponse;
use App\Models\TrackingContainer;
use App\Models\TrackingEvent;
use App\Models\TrackingEventMapping;
use App\Models\TrackingLocation;
use App\Models\TrackingRawPayload;
use App\Models\TrackingShipment;
use App\Models\TrackingTransportCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DcsaEventIngestionService
{
    public function __construct(private readonly LocationCatalogMatcher $locationCatalogs)
    {
    }

    /** @return array{created: int, updated: int}
     * @throws \Throwable
     */
    public function ingest(TrackingShipment $shipment, CarrierTrackingResponse $response): array
    {
        $encoded = json_encode($this->sorted($response->events), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $checksum = hash('sha256', $encoded);

        $raw = TrackingRawPayload::query()->firstOrCreate(
            [
                'carrier_id' => $shipment->carrier_id,
                'checksum' => $checksum
            ],
            [
                'tracking_shipment_id' => $shipment->id,
                'request_reference_type' => 'BOOKING',
                'request_reference' => $shipment->booking_reference,
                'api_version' => $response->apiVersion,
                'http_status' => $response->httpStatus,
                'payload' => $response->events,
                'processing_status' => 'PENDING',
                'received_at' => now(),
            ],
        );

        if (! $raw->wasRecentlyCreated && $raw->processing_status === 'PROCESSED') {

            return ['created' => 0, 'updated' => 0];

        }

        return DB::transaction(function () use ($shipment, $response, $raw) {

            $created = 0;

            $updated = 0;

            foreach ($response->events as $event) {

                $type = strtoupper((string) ($event['eventType'] ?? 'UNKNOWN'));

                $code = $this->eventCode($type, $event);

                $location = $this->location($event);

                $container = $this->container($shipment, $event);

                $transportCall = $this->transportCall($shipment, $event, $location);

                $fingerprint = hash('sha256', json_encode($this->sorted($event), JSON_THROW_ON_ERROR));

                $attributes = [
                    'tracking_container_id' => $container?->id,
                    'tracking_transport_call_id' => $transportCall?->id,
                    'tracking_location_id' => $location?->id,
                    'tracking_raw_payload_id' => $raw->id,
                    'source_event_id' => $event['eventID'] ?? null,
                    'event_type' => $type,
                    'event_classifier_code' => $event['eventClassifierCode'] ?? null,
                    'event_code' => $code,
                    'canonical_status' => $this->canonicalStatus($shipment, $type, $code, $event['eventClassifierCode'] ?? null),
                    'event_date_time' => $event['eventDateTime'],
                    'event_created_date_time' => $event['eventCreatedDateTime'] ?? null,
                    'document_type_code' => $event['documentTypeCode'] ?? null,
                    'document_id' => $event['documentID'] ?? null,
                    'description' => $event['eventTypeDescription'] ?? null,
                    'references' => $event['references'] ?? null,
                ];

                $trackingEvent = TrackingEvent::query()->updateOrCreate(
                    [
                        'tracking_shipment_id' => $shipment->id,
                        'fingerprint' => $fingerprint
                    ],
                    $attributes,
                );

                $trackingEvent->wasRecentlyCreated ? $created++ : $updated++;

            }

            $latest = $shipment->events()->where('event_classifier_code', 'ACT')->latest('event_date_time')->first();

            $shipment->update([
                'canonical_status' => $latest?->canonical_status ?? $shipment->canonical_status,
                'last_synced_at' => now(),
                'next_sync_at' => now()->addMinutes(config('tracking.sync.interval_minutes')),
                'consecutive_failures' => 0,
                'last_error' => null,
            ]);

            $raw->update(['processing_status' => 'PROCESSED', 'processed_at' => now()]);

            return compact('created', 'updated');

        });

    }

    private function eventCode(string $type, array $event): string
    {
        return match ($type) {
            'EQUIPMENT' => $event['equipmentEventTypeCode'] ?? 'UNKNOWN',
            'TRANSPORT' => $event['transportEventTypeCode'] ?? 'UNKNOWN',
            'SHIPMENT' => $event['shipmentEventTypeCode'] ?? 'UNKNOWN',
            default => 'UNKNOWN',
        };
    }

    private function canonicalStatus(TrackingShipment $shipment, string $type, string $code, ?string $classifier): string
    {

        $mapping = TrackingEventMapping::query()
            ->where(fn ($query) => $query->whereNull('carrier_id')->orWhere('carrier_id', $shipment->carrier_id))
            ->where('source_event_type', $type)
            ->where('source_event_code', $code)
            ->where(fn ($query) => $query->whereNull('source_classifier_code')->orWhere('source_classifier_code', $classifier))
            ->orderByRaw('carrier_id is null')
            ->first();

        return $mapping?->canonical_status ?? 'UNKNOWN';

    }

    private function location(array $event): ?TrackingLocation
    {
        $data = $event['eventLocation'] ?? data_get($event, 'transportCall.location');

        if (! $data) {

            return null;

        }

        $unLocationCode = $data['UNLocationCode'] ?? data_get($event, 'transportCall.UNLocationCode');
        $facilitySmdgCode = data_get($data, 'facility.SMDGCode');
        $eventDate = ! empty($event['eventDateTime']) ? Carbon::parse($event['eventDateTime']) : null;
        $catalog = $this->locationCatalogs->find(
            $unLocationCode,
            $facilitySmdgCode,
            $eventDate,
            $data['locationName'] ?? null,
        );

        return TrackingLocation::query()->updateOrCreate(
            [
                'un_location_code' => $unLocationCode ?: $catalog?->un_location_code,
                'facility_smdg_code' => $facilitySmdgCode,
            ],
            [
                'tracking_location_catalog_id' => $catalog?->id,
                'facility_type_code' => data_get($event, 'transportCall.facilityTypeCode'),
                'name' => $data['locationName'] ?? $catalog?->name,
                'country_code' => $data['countryCode'] ?? ($catalog ? substr($catalog->un_location_code, 0, 2) : null),
                'latitude' => $catalog?->latitude,
                'longitude' => $catalog?->longitude,
            ],
        );

    }

    private function container(TrackingShipment $shipment, array $event): ?TrackingContainer
    {
        if (empty($event['equipmentReference'])) {

            return null;

        }

        return TrackingContainer::query()->updateOrCreate(
            [
                'tracking_shipment_id' => $shipment->id,
                'equipment_reference' => $event['equipmentReference']
            ],
            [
                'iso_equipment_code' => $event['ISOEquipmentCode'] ?? null,
                'empty_indicator_code' => $event['emptyIndicatorCode'] ?? null,
            ],
        );

    }

    private function transportCall(TrackingShipment $shipment, array $event, ?TrackingLocation $location): ?TrackingTransportCall
    {
        $call = $event['transportCall'] ?? null;

        if (! $call) {

            return null;

        }

        $externalId = $call['transportCallID'] ?? hash('sha256', json_encode($this->sorted($call), JSON_THROW_ON_ERROR));

        return TrackingTransportCall::query()->updateOrCreate(
            [
                'tracking_shipment_id' => $shipment->id,
                'external_transport_call_id' => $externalId
            ],
            [
                'tracking_location_id' => $location?->id,
                'mode_of_transport' => $call['modeOfTransport'] ?? null,
                'vessel_imo_number' => data_get($call, 'vessel.vesselIMONumber'),
                'vessel_name' => data_get($call, 'vessel.vesselName'),
                'export_voyage_number' => $call['exportVoyageNumber'] ?? null,
                'import_voyage_number' => $call['importVoyageNumber'] ?? null,
                'carrier_service_code' => $call['carrierServiceCode'] ?? null,
                'universal_service_reference' => $call['universalServiceReference'] ?? null,
            ],
        );
    }

    private function sorted(array $value): array
    {
        foreach ($value as &$item) {

            if (is_array($item)) {

                $item = $this->sorted($item);

            }

        }

        unset($item);

        if (! array_is_list($value)) {

            ksort($value);

        }

        return $value;
    }
}
