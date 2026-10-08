<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $call = $this->transportCall;

        return [
            'id' => $this->id,
            'container_id' => $this->tracking_container_id,
            'event_date' => $this->event_date_time?->toIso8601String(),
            'original_date' => $this->event_date_time?->toIso8601String(),
            'description' => $this->description,
            'event_code' => $this->event_code,
            'event_type' => $this->event_type,
            'event_classifier_code' => $this->event_classifier_code,
            'is_actual' => $this->event_classifier_code === 'ACT',
            'route_type' => strtoupper((string) $call?->mode_of_transport) === 'VESSEL' ? 'SEA' : 'LAND',
            'status' => $this->canonical_status,
            'transport_type' => $call?->mode_of_transport,
            'voyage' => $call?->export_voyage_number ?? $call?->import_voyage_number,
            'location' => $this->location ? (new TrackingLocationResource($this->location))->resolve($request) : null,
            'vessel' => $call ? [
                'name' => $call->vessel_name,
                'imo' => $call->vessel_imo_number,
            ] : null,
            'facility' => $this->location?->catalog ? [
                'name' => $this->location->catalog->name,
                'locode' => $this->location->catalog->un_location_code,
                'smdg_code' => $this->location->catalog->terminal_code,
            ] : null,
        ];
    }

}
