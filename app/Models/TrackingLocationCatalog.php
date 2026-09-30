<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingLocationCatalog extends Model
{
    protected $fillable = [
        'un_location_code',
        'alternative_un_location_code',
        'terminal_code',
        'name',
        'latitude',
        'longitude',
        'valid_from',
        'valid_to',
        'source_version',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function trackingLocations(): HasMany
    {
        return $this->hasMany(TrackingLocation::class);
    }
}
