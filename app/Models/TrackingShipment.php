<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingShipment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {

        return [
            'sync_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'next_sync_at' => 'datetime'
        ];

    }

    public function carrier(): BelongsTo
    {

        return $this->belongsTo(Carrier::class);

    }

    public function containers(): HasMany
    {

        return $this->hasMany(TrackingContainer::class);

    }

    public function events(): HasMany
    {

        return $this->hasMany(TrackingEvent::class);

    }

}
