<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['event_date_time' => 'datetime', 'event_created_date_time' => 'datetime', 'references' => 'array'];
    }
}
