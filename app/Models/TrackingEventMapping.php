<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEventMapping extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_terminal' => 'boolean'];
    }
}
