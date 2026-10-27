<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingVoiceAsset extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['spec' => 'array', 'usage' => 'array', 'duration_seconds' => 'float', 'reserved_microusd' => 'integer'];
    }
}
