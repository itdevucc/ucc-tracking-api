<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {

        return ['event_date_time' => 'datetime', 'event_created_date_time' => 'datetime', 'references' => 'array'];

    }

    public function location(): BelongsTo
    {

        return $this->belongsTo(TrackingLocation::class, 'tracking_location_id');

    }

    public function transportCall(): BelongsTo
    {

        return $this->belongsTo(TrackingTransportCall::class, 'tracking_transport_call_id');

    }
}
