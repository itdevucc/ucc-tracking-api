<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $events = $this->events->sortBy('event_date_time')->values();
        $departure = $events->first(fn ($event) => in_array($event->event_code, ['LOAD', 'DEPA'], true));
        $arrival = $events->first(fn ($event) => in_array($event->event_code, ['ARRI', 'DISC'], true));

        return [
            'id' => $this->id,
            'shipment_id' => $this->tracking_shipment_id,
            'container_number' => $this->equipment_reference,
            'iso_code' => $this->iso_equipment_code,
            'status' => $this->canonical_status,
            'first_event' => $events->first() ? (new TrackingEventResource($events->first()))->resolve($request) : null,
            'departure_event' => $departure ? (new TrackingEventResource($departure))->resolve($request) : null,
            'arrival_event' => $arrival ? (new TrackingEventResource($arrival))->resolve($request) : null,
            'last_event' => $events->last() ? (new TrackingEventResource($events->last()))->resolve($request) : null,
            'events' => TrackingEventResource::collection($events)->resolve($request),
        ];
    }
}
