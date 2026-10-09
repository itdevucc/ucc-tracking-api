<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends Model
{
    protected $fillable = ['code', 'scac', 'name', 'connector', 'tracking_api_version', 'is_active', 'settings'];

    protected function casts(): array
    {

        return ['is_active' => 'boolean', 'settings' => 'array'];

    }

    public function shipments(): HasMany
    {

        return $this->hasMany(TrackingShipment::class);

    }
}
