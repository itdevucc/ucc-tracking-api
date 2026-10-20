<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tracking\DcsaEventFields;
use App\Tracking\TrackingEventSchedule;

class TrackingShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        $events = $this->events->sortBy('event_date_time')->values();

        $routePoints = $this->routePoints($events, $request);

        $firstPoint = $routePoints->first();

        $lastPoint = $routePoints->last();

        $departure = $this->scheduleEvent($events, ['DEPA', 'LOAD'], false);

        $arrival = $this->scheduleEvent($events, ['ARRI', 'DISC'], true);

        $documentReference = $this->documentReference($events);

        $vesselCall = $events
            ->first(fn ($event) => filled($event->transportCall?->vessel_name))
            ?->transportCall;

        return [
            'id' => $this->id,
            'booking_number' => $this->booking_reference,
            'shipment_number' => $documentReference ?? $this->booking_reference,
            'transport_document_reference' => $documentReference,
            'bl_number' => $documentReference,
            'sealine' => $this->carrier->scac,
            'sealine_name' => $this->carrier->name,
            'carrier_name' => $this->carrier->name,
            'carrier_departure_name' => $this->carrier->name,
            'tracking_api_version' => $this->carrier->tracking_api_version,
            'tracking_source' => $this->carrier->name.' · DCSA Track & Trace '.($this->carrier->tracking_api_version ?? ''),
            'etd' => $departure?->event_date_time?->toIso8601String(),
            'eta' => $arrival?->event_date_time?->toIso8601String(),
            'pol_etd' => $departure?->event_date_time?->toIso8601String(),
            'pod_eta' => $arrival?->event_date_time?->toIso8601String(),
            'vessel' => $vesselCall ? [
                'name' => $vesselCall->vessel_name,
                'imo' => $vesselCall->vessel_imo_number,
                'voyage' => $vesselCall->export_voyage_number ?? $vesselCall->import_voyage_number,
            ] : null,
            'vessel_name' => $vesselCall?->vessel_name,
            'voyage_number' => $vesselCall?->export_voyage_number ?? $vesselCall?->import_voyage_number,
            'container_count' => $this->containers->count(),
            'link' => null,
            'metadata' => [
                'shipmentType' => 'BK',
                'shipmentNumber' => $this->booking_reference,
                'sealine' => $this->carrier->scac,
                'sealineName' => $this->carrier->name,
                'shippingStatus' => $this->canonical_status,
                'updatedAt' => $this->last_synced_at?->toIso8601String(),
                'warnings' => [],
            ],
            'path_data' => $this->pathData($routePoints),
            'route_points' => $routePoints->values()->all(),
            'ais_data' => null,
            'pod_code' => $this->pod_code ?? data_get($lastPoint, 'location.locode'),
            'pol_code' => data_get($firstPoint, 'location.locode'),
            'shipping_status' => $this->canonical_status,
            'pol_name' => data_get($firstPoint, 'location.name'),
            'pod_name' => $this->pod_name ?? data_get($lastPoint, 'location.name'),
            'tracking_completed_at' => $this->tracking_completed_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'events' => TrackingEventResource::collection($events)->resolve($request),
            'containers' => TrackingContainerResource::collection(
                $this->containersWithShipmentEvents($events),
            )->resolve($request),
            'stops' => $routePoints->values()->map(function ($point, $index) use ($routePoints) {

                return [
                    ...$point,
                    'stop_type' => $index === 0 ? 'POL' : ($index === $routePoints->count() - 1 ? 'POD' : 'TSP'),
                    'predictive_eta' => $point['is_actual'] ? null : $point['date'],
                ];

            })->all(),
        ];

    }

    private function containersWithShipmentEvents($events)
    {

        $shipmentEvents = $events->whereNull('tracking_container_id');

        return $this->containers->map(function ($container) use ($shipmentEvents) {

            $documents = $container->events->flatMap(fn ($event) => DcsaEventFields::transportDocuments(['references' => $event->references ?? []]))->unique()->all();

            $applicableEvents = $shipmentEvents->filter(function ($event) use ($documents) {

                $eventDocuments = DcsaEventFields::transportDocuments(['references' => $event->references ?? []]);

                return $documents === [] || $eventDocuments === [] || array_intersect($documents, $eventDocuments) !== [];

            });

            $container->setRelation(
                'events',
                $container->events
                    ->concat($applicableEvents)
                    ->unique('id')
                    ->sortBy('event_date_time')
                    ->values(),
            );

            return $container;

        });

    }

    private function scheduleEvent($events, array $codes, bool $last)
    {

        return TrackingEventSchedule::select($events, $codes, $last);

    }

    private function documentReference($events): ?string
    {

        $documents = collect();

        foreach ($events as $event) {

            $documents = $documents->concat(DcsaEventFields::transportDocuments(['references' => $event->references ?? []]));

            if (filled($event->document_id) && in_array(strtoupper((string) $event->document_type_code), ['BOL', 'BL', 'TRD'], true)) {

                $documents->push((string) $event->document_id);

            }

        }

        if ($documents->isNotEmpty()) {

            return $documents->unique()->sort()->implode(', ');

        }

        return filled($this->transport_document_reference)
            ? $this->transport_document_reference
            : null;

    }

    private function routePoints($events, Request $request)
    {

        $points = $events
            ->whereNotNull('tracking_location_id')
            ->map(function ($event) use ($request) {

                $latitude = $event->location?->catalog?->latitude ?? $event->location?->latitude;

                $longitude = $event->location?->catalog?->longitude ?? $event->location?->longitude;

                if ($latitude === null || $longitude === null) {

                    return null;

                }

                return [
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                    'date' => $event->event_date_time?->toIso8601String(),
                    'is_actual' => $event->event_classifier_code === 'ACT',
                    'event_code' => $event->event_code,
                    'route_type' => strtoupper((string) ($event->transportCall?->mode_of_transport ?? '')) === 'VESSEL' ? 'SEA' : 'LAND',
                    'transport_type' => $event->transportCall?->mode_of_transport,
                    'location' => (new TrackingLocationResource($event->location))->resolve($request),
                    'event' => [
                        'id' => $event->id,
                        'code' => $event->event_code,
                        'description' => $event->description,
                        'type' => $event->event_type,
                        'classifier_code' => $event->event_classifier_code,
                        'status' => $event->canonical_status,
                        'date' => $event->event_date_time?->toIso8601String(),
                        'is_actual' => $event->event_classifier_code === 'ACT',
                        'transport_type' => $event->transportCall?->mode_of_transport,
                        'vessel_name' => $event->transportCall?->vessel_name,
                        'voyage' => $event->transportCall?->export_voyage_number
                            ?? $event->transportCall?->import_voyage_number,
                    ],
                ];

            })
            ->filter()
            ->values();

        return $points->reduce(function ($result, $point) {

            $previous = $result->last();

            if (! $previous || data_get($previous, 'location.id') !== data_get($point, 'location.id')) {

                $result->push($point);

            } elseif (data_get($point, 'route_type') === 'SEA'
                || data_get($previous, 'route_type') !== 'SEA') {

                $result->put($result->count() - 1, $point);

            }

            return $result;

        }, collect());

    }

    private function pathData($routePoints): array
    {

        return $routePoints
            ->groupBy('route_type')
            ->map(fn ($path, $routeType) => [
                'routeType' => $routeType,
                'path' => $path->map(fn ($point) => [
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    'updatedAt' => $point['date'],
                    'isActual' => $point['is_actual'],
                ])->values()->all(),
            ])
            ->values()
            ->all();

    }
}
