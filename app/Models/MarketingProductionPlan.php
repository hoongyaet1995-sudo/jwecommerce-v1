<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingProductionPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'manifest' => 'array', 'project_version' => 'integer', 'confirmed_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
