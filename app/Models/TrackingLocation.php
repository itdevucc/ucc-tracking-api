<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingLocation extends Model
{
    protected $guarded = [];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(TrackingLocationCatalog::class, 'tracking_location_catalog_id');
    }
}
