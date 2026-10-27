<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingProduction extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array', 'new_reserved_microusd' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
