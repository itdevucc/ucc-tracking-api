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

        if ($shipment->stopSyncIfDestinationReached()) {

            return ['created' => 0, 'updated' => 0];

        }

        $encoded = json_encode($this->sorted($response->events), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $checksum = hash('sha256', config('tracking.ingestion_version').'|'.$shipment->booking_reference.'|'.$encoded);

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

        return DB::transaction(function () use ($shipment, $response, $raw) {

            // Solo reemplazar después de recibir todas las páginas válidas.
            // Una respuesta vacía no elimina el historial disponible.
            if ($response->events !== []) {

                $shipment->events()->delete();

            }

            $created = 0;

            $updated = 0;

            foreach ($response->events as $event) {

                $type = strtoupper((string) ($event['eventType'] ?? 'UNKNOWN'));

                $code = $this->eventCode($type, $event);

                $mapping = $this->eventMapping(
                    $shipment,
                    $type,
                    $code,
                    $event['eventClassifierCode'] ?? null,
                );

                $location = $this->location($event);

                $container = $this->container($shipment, $event);

                $transportCall = $this->transportCall($shipment, $event, $location);

                $fingerprint = hash('sha256', filled($event['eventID'] ?? null)
                    ? 'eventID:'.$event['eventID']
                    : json_encode($this->sorted($event), JSON_THROW_ON_ERROR));

                $attributes = [
                    'tracking_container_id' => $container?->id,
                    'tracking_transport_call_id' => $transportCall?->id,
                    'tracking_location_id' => $location?->id,
                    'tracking_raw_payload_id' => $raw->id,
                    'source_event_id' => $event['eventID'] ?? null,
                    'event_type' => $type,
                    'event_classifier_code' => $event['eventClassifierCode'] ?? null,
                    'event_code' => $code,
                    'canonical_status' => $mapping?->canonical_status ?? 'UNKNOWN',
                    'event_date_time' => $event['eventDateTime'],
                    'event_created_date_time' => $event['eventCreatedDateTime'] ?? null,
                    'document_type_code' => $event['documentTypeCode'] ?? null,
                    'document_id' => $event['documentID'] ?? null,
                    'description' => $this->eventDescription($event, $mapping?->label_es),
                    'references' => $event['documentReferences'] ?? $event['references'] ?? null,
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

            if ($response->events !== []) {

                $shipment->containers()->whereDoesntHave('events')->delete();

            }

            $latest = $shipment->events()->where('event_classifier_code', 'ACT')->latest('event_date_time')->first();

            $shipment->update([
                'canonical_status' => $latest?->canonical_status ?? ($response->events === [] ? $shipment->canonical_status : 'UNKNOWN'),
                'last_synced_at' => now(),
                'next_sync_at' => now()->addMinutes(config('tracking.sync.interval_minutes')),
                'consecutive_failures' => 0,
                'last_error' => null,
            ]);

            $raw->update(['processing_status' => 'PROCESSED', 'processed_at' => now()]);

            $shipment->stopSyncIfDestinationReached();

            return compact('created', 'updated');

        });

    }

    private function eventDescription(array $event, ?string $fallback): ?string
    {

        foreach (['description', 'eventTypeDescription'] as $field) {

            if (is_string($event[$field] ?? null) && filled($event[$field])) {

                return $event[$field];

            }

        }

        return $fallback;

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

    private function eventMapping(TrackingShipment $shipment, string $type, string $code, ?string $classifier): ?TrackingEventMapping
    {

        return TrackingEventMapping::query()
            ->where(fn ($query) => $query->whereNull('carrier_id')->orWhere('carrier_id', $shipment->carrier_id))
            ->where('source_event_type', $type)
            ->where('source_event_code', $code)
            ->where(fn ($query) => $query->whereNull('source_classifier_code')->orWhere('source_classifier_code', $classifier))
            ->orderByRaw('carrier_id is null')
            ->first();

    }

    private function location(array $event): ?TrackingLocation
    {

        $eventLocation = is_array($event['eventLocation'] ?? null)
            ? $event['eventLocation']
            : [];

        $transportLocation = is_array(data_get($event, 'transportCall.location'))
            ? data_get($event, 'transportCall.location')
            : [];

        $data = array_replace($transportLocation, $eventLocation);

        $unLocationCode = strtoupper(trim((string) (
            $data['UNLocationCode']
            ?? $data['unLocationCode']
            ?? $event['UNLocationCode']
            ?? $event['unLocationCode']
            ?? data_get($event, 'transportCall.UNLocationCode')
            ?? data_get($event, 'transportCall.unLocationCode')
            ?? data_get($event, 'transportCall.location.UNLocationCode')
            ?? data_get($event, 'transportCall.location.unLocationCode')
            ?? ''
        )));

        $facilitySmdgCode = data_get($data, 'facility.SMDGCode');

        foreach ([$eventLocation, $transportLocation, $event['transportCall'] ?? []] as $facilityData) {

            if (! filled($facilitySmdgCode)
                && strtoupper((string) ($facilityData['facilityCodeListProvider'] ?? '')) === 'SMDG') {

                $facilitySmdgCode = $facilityData['facilityCode'] ?? null;

            }

        }

        $facilitySmdgCode = filled($facilitySmdgCode) ? strtoupper(trim($facilitySmdgCode)) : null;

        $locationName = trim((string) ($data['locationName'] ?? ''));

        if ($unLocationCode === '' && $locationName === '') {

            return null;

        }

        $eventDate = ! empty($event['eventDateTime']) ? Carbon::parse($event['eventDateTime']) : null;

        $catalog = $this->locationCatalogs->find(
            $unLocationCode,
            $facilitySmdgCode,
            $eventDate,
            $locationName,
        );

        // Si la terminal no está catalogada, usar coordenadas del puerto.
        if (! $catalog && $facilitySmdgCode && $unLocationCode !== '') {

            $catalog = $this->locationCatalogs->find($unLocationCode, null, $eventDate, $locationName);

        }

        $resolvedCode = $unLocationCode ?: $catalog?->un_location_code;

        $location = null;

        if (filled($resolvedCode)) {

            $location = TrackingLocation::query()
                ->where('un_location_code', $resolvedCode)
                ->where('facility_smdg_code', $facilitySmdgCode)
                ->first();

        }

        if (! $location && filled($locationName)) {

            $location = TrackingLocation::query()
                ->whereNull('un_location_code')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($locationName)])
                ->where('facility_smdg_code', $facilitySmdgCode)
                ->first();

        }

        $location ??= new TrackingLocation();

        $location->fill([
            'un_location_code' => $resolvedCode ?: $location->un_location_code,
            'facility_smdg_code' => $facilitySmdgCode ?: $location->facility_smdg_code,
            'tracking_location_catalog_id' => $catalog?->id ?? $location->tracking_location_catalog_id,
            'facility_type_code' => data_get($event, 'transportCall.facilityTypeCode')
                ?? $location->facility_type_code,
            'name' => $locationName ?: ($catalog?->name ?? $location->name),
            'country_code' => $data['countryCode']
                ?? ($resolvedCode ? substr($resolvedCode, 0, 2) : $location->country_code),
            'latitude' => $catalog?->latitude ?? $location->latitude,
            'longitude' => $catalog?->longitude ?? $location->longitude,
        ])->save();

        return $location;

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
