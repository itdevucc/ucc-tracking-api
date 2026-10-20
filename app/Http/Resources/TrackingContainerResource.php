<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tracking\DcsaEventFields;
use App\Tracking\TrackingEventSchedule;

class TrackingContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        $events = $this->events->sortBy('event_date_time')->values();

        $departure = TrackingEventSchedule::select($events, ['LOAD', 'DEPA'], false);

        $arrival = TrackingEventSchedule::select($events, ['ARRI', 'DISC'], true);

        $documents = $events->flatMap(fn ($event) => DcsaEventFields::transportDocuments(['references' => $event->references ?? []]))->unique()->sort()->values();

        return [
            'id' => $this->id,
            'shipment_id' => $this->tracking_shipment_id,
            'container_number' => $this->equipment_reference,
            'transport_document_reference' => $documents->isEmpty() ? null : $documents->implode(', '),
            'iso_code' => $this->iso_equipment_code,
            'status' => $events->last()?->canonical_status ?? $this->canonical_status,
            'event_count' => $events->count(),
            'equipment_type' => $this->iso_equipment_code,
            'first_event' => $events->first() ? (new TrackingEventResource($events->first()))->resolve($request) : null,
            'departure_event' => $departure ? (new TrackingEventResource($departure))->resolve($request) : null,
            'arrival_event' => $arrival ? (new TrackingEventResource($arrival))->resolve($request) : null,
            'last_event' => $events->last() ? (new TrackingEventResource($events->last()))->resolve($request) : null,
            'events' => TrackingEventResource::collection($events)->resolve($request),
        ];

    }
}
