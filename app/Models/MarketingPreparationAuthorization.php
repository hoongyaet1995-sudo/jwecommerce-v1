<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingPreparationAuthorization extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scope' => 'array', 'claims' => 'array', 'expires_at' => 'datetime', 'story_version' => 'integer', 'cap_microusd' => 'integer', 'spent_microusd' => 'integer', 'reserved_microusd' => 'integer', 'unknown_microusd' => 'integer'];
    }
}
