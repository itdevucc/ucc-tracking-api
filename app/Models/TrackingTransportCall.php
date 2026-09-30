<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingTransportCall extends Model
{
    protected $guarded = [];

    public function location(): BelongsTo
    {
        return $this->belongsTo(TrackingLocation::class, 'tracking_location_id');
    }
}
