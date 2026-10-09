<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingContainer extends Model
{
    protected $guarded = [];

    public function events(): HasMany
    {

        return $this->hasMany(TrackingEvent::class);

    }
}
