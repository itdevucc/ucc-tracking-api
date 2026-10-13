<?php

namespace App\Tracking;

use App\Data\CarrierTrackingResponse;
use App\Models\Carrier;
use App\Models\TrackingContainer;
use App\Models\TrackingEvent;
use App\Models\TrackingLocation;
use App\Models\TrackingShipment;
use App\Models\TrackingTransportCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class DirectTrackingSnapshot
{
    public function __construct(private readonly LocationCatalogMatcher $locations) {}

    /** Construye relaciones en memoria; no guarda modelos ni consulta shipments. */
    public function build(string $booking, Carrier $carrier, CarrierTrackingResponse $response, Collection $mappings): TrackingShipment
    {

        $shipment = new TrackingShipment(['id' => 0, 'carrier_id' => $carrier->id, 'booking_reference' => $booking, 'last_synced_at' => now()]);

        $events = collect();

        $containers = collect();

        $locations = [];

        foreach ($response->events as $index => $raw) {

            $reference = DcsaEventFields::equipmentReference($raw);

            if ($reference && ! $containers->has($reference)) {

                $containers->put($reference, new TrackingContainer(['id' => $containers->count() + 1, 'tracking_shipment_id' => 0, 'equipment_reference' => $reference]));

            }

            $container = $reference ? $containers->get($reference) : null;

            if ($container && filled($raw['ISOEquipmentCode'] ?? null)) {

                $container->iso_equipment_code = $raw['ISOEquipmentCode'];

            }

            $call = $raw['transportCall'] ?? [];

            $location = $this->location($raw, $locations);

            $transport = $call ? new TrackingTransportCall([
                'id' => $index + 1,
                'mode_of_transport' => $call['modeOfTransport'] ?? null,
                'vessel_name' => data_get($call, 'vessel.vesselName'),
                'vessel_imo_number' => data_get($call, 'vessel.vesselIMONumber'),
                'export_voyage_number' => $call['exportVoyageNumber'] ?? null,
                'import_voyage_number' => $call['importVoyageNumber'] ?? null,
            ]) : null;

            $code = DcsaEventFields::code($raw);

            $type = strtoupper((string) ($raw['eventType'] ?? 'UNKNOWN'));

            $classifier = $raw['eventClassifierCode'] ?? null;

            $mapping = $mappings->first(fn ($mapping) => $mapping->source_event_type === $type && $mapping->source_event_code === $code
                && ($mapping->source_classifier_code === null || $mapping->source_classifier_code === $classifier));

            $event = new TrackingEvent([
                'id' => $index + 1,
                'tracking_shipment_id' => 0,
                'tracking_container_id' => $container?->id,
                'tracking_location_id' => $location?->id,
                'source_event_id' => $raw['eventID'] ?? $raw['eventId'] ?? null,
                'event_type' => $type,
                'event_code' => $code,
                'event_classifier_code' => $classifier,
                'event_date_time' => $raw['eventDateTime'],
                'description' => DcsaEventFields::description($raw, $mapping?->label_es),
                'canonical_status' => $mapping?->canonical_status ?? 'UNKNOWN',
                'document_id' => $raw['documentID'] ?? null,
                'document_type_code' => $raw['documentTypeCode'] ?? null,
                'references' => $raw['documentReferences'] ?? $raw['references'] ?? null,
            ]);

            // Sin persistencia, conservar el offset original del API.
            $event->setRawAttributes([...$event->getAttributes(), 'event_date_time' => $raw['eventDateTime']]);

            $event->setRelation('location', $location)->setRelation('transportCall', $transport);

            $events->push($event);

        }

        foreach ($containers as $container) {

            $container->setRelation('events', $events->where('tracking_container_id', $container->id)->values());

        }

        $shipment->canonical_status = $events->where('event_classifier_code', 'ACT')->sortBy('event_date_time')->last()?->canonical_status ?? 'UNKNOWN';

        return $shipment->setRelation('carrier', $carrier)->setRelation('containers', $containers->values())->setRelation('events', $events);

    }

    private function location(array $event, array &$cache): ?TrackingLocation
    {

        $call = $event['transportCall'] ?? [];

        $nested = is_array($call['location'] ?? null) ? $call['location'] : [];

        $explicit = is_array($event['eventLocation'] ?? null) ? $event['eventLocation'] : [];

        $data = array_replace($call, $nested, $explicit);

        $port = strtoupper(trim((string) ($data['UNLocationCode'] ?? $data['unLocationCode'] ?? $event['UNLocationCode'] ?? $event['unLocationCode'] ?? '')));

        $name = $data['locationName'] ?? '';

        $terminal = data_get($data, 'facility.SMDGCode');

        if (! filled($terminal) && strtoupper((string) ($data['facilityCodeListProvider'] ?? '')) === 'SMDG') {

            $terminal = $data['facilityCode'] ?? null;

        }

        if ($port === '' && $name === '') {

            return null;

        }

        $date = Carbon::parse($event['eventDateTime']);

        $key = $port.'|'.$terminal.'|'.$name.'|'.$date->toDateString();

        if (isset($cache[$key])) {

            return $cache[$key];

        }

        $catalog = $this->locations->find($port, $terminal, $date, $name);

        if (! $catalog && filled($terminal) && $port !== '') {

            $catalog = $this->locations->find($port, null, $date, $name);

        }

        $location = new TrackingLocation([
            'id' => (int) sprintf('%u', crc32(($port ?: $name).'|'.$terminal)),
            'un_location_code' => $port ?: $catalog?->un_location_code,
            'name' => $name ?: ($catalog?->name ?? $port),
            'country_code' => $port ? substr($port, 0, 2) : $catalog?->country_code,
            'facility_smdg_code' => $terminal,
            'latitude' => $catalog?->latitude,
            'longitude' => $catalog?->longitude,
        ]);

        $location->setRelation('catalog', $catalog);

        return $cache[$key] = $location;

    }
}
