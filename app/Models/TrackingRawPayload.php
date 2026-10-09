<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingRawPayload extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {

        return ['payload' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];

    }
}
