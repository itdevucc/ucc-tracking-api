<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackingLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'name' => $this->name ?? $this->catalog?->name,
            'locode' => $this->un_location_code ?? $this->catalog?->un_location_code,
            'country_code' => $this->country_code ?? $this->catalog?->country_code,
            'lat' => ($this->catalog?->latitude ?? $this->latitude) !== null
                ? (float) ($this->catalog?->latitude ?? $this->latitude)
                : null,
            'lng' => ($this->catalog?->longitude ?? $this->longitude) !== null
                ? (float) ($this->catalog?->longitude ?? $this->longitude)
                : null,
        ];

    }
}
