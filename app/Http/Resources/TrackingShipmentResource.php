<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $events = $this->events->sortBy('event_date_time')->values();
        $firstLocation = $events->firstWhere('tracking_location_id', '!=', null)?->location;
        $lastLocation = $events->whereNotNull('tracking_location_id')->last()?->location;

        return [
            'id' => $this->id,
            'booking_number' => $this->booking_reference,
            'shipment_number' => $this->transport_document_reference ?? $this->booking_reference,
            'sealine' => $this->carrier->scac,
            'sealine_name' => $this->carrier->name,
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
            'path_data' => $this->pathData($events),
            'ais_data' => null,
            'pod_code' => $lastLocation?->un_location_code,
            'pol_code' => $firstLocation?->un_location_code,
            'shipping_status' => $this->canonical_status,
            'pod_eta' => null,
            'pol_name' => $firstLocation?->name,
            'pod_name' => $lastLocation?->name,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'containers' => TrackingContainerResource::collection($this->containers)->resolve($request),
            'stops' => $events
                ->whereNotNull('tracking_location_id')
                ->map(fn ($event) => [
                    'date' => $event->event_date_time?->toIso8601String(),
                    'is_actual' => $event->event_classifier_code === 'ACT',
                    'event_code' => $event->event_code,
                    'location' => (new TrackingLocationResource($event->location))->resolve($request),
                ])
                ->values()
                ->all(),
        ];
    }

    private function pathData($events): array
    {
        return $events
            ->whereNotNull('tracking_location_id')
            ->map(function ($event) {
                $latitude = $event->location?->catalog?->latitude ?? $event->location?->latitude;
                $longitude = $event->location?->catalog?->longitude ?? $event->location?->longitude;

                if ($latitude === null || $longitude === null) {
                    return null;
                }

                return [
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                    'updatedAt' => $event->event_date_time?->toIso8601String(),
                    'routeType' => strtoupper((string) ($event->transportCall?->mode_of_transport ?? '')) === 'VESSEL'
                        ? 'SEA'
                        : 'LAND',
                ];
            })
            ->filter()
            ->values()
            ->groupBy('routeType')
            ->map(fn ($path, $routeType) => [
                'routeType' => $routeType,
                'path' => $path->map(fn ($point) => collect($point)->except('routeType')->all())->values()->all(),
            ])
            ->values()
            ->all();
    }
}
