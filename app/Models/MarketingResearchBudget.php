<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingResearchBudget extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_microusd' => 'integer', 'held_microusd' => 'integer', 'settled_microusd' => 'integer', 'enabled' => 'boolean'];
    }
}
