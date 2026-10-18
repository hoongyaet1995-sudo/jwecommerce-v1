<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchExposureObservation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'observed_at' => 'datetime'];
    }
}
