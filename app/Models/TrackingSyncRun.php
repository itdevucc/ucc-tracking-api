<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {

        return ['request_parameters' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    }
}
