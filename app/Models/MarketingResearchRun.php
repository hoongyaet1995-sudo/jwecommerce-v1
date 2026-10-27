<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingResearchRun extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['input_snapshot' => 'array', 'query_plan' => 'array', 'sources' => 'array', 'analysis' => 'array', 'calls' => 'array', 'reported_usage' => 'array', 'pricing_snapshot' => 'array', 'reserved_microusd' => 'integer', 'reported_cost_microusd' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
