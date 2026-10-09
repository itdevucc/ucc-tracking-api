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
            'next_sync_at' => 'datetime',
            'tracking_completed_at' => 'datetime',
            'expected_container_count' => 'integer',
        ];

    }

    public function carrier(): BelongsTo
    {

        return $this->belongsTo(Carrier::class);

    }

    public function stopSyncIfDestinationReached(): bool
    {

        if ($this->tracking_completed_at) {

            return true;

        }

        if (! $this->pod_code || ! $this->expected_container_count) {

            return false;

        }

        $containers = $this->containers()->get();

        if ($containers->count() < $this->expected_container_count || $containers->isEmpty()) {

            return false;

        }

        $discharged = $this->events()
            ->where('event_type', 'EQUIPMENT')
            ->where('event_code', 'DISC')
            ->where('event_classifier_code', 'ACT')
            ->where('event_date_time', '<=', now())
            ->whereHas('transportCall', fn ($query) => $query->where('mode_of_transport', 'VESSEL'))
            ->whereHas('location', fn ($query) => $query->where('un_location_code', $this->pod_code))
            ->pluck('tracking_container_id');

        if ($containers->contains(fn ($container) => ! $discharged->contains($container->id))) {

            return false;

        }

        $this->update([
            'sync_enabled' => false,
            'next_sync_at' => null,
            'tracking_completed_at' => now(),
        ]);

        return true;

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
