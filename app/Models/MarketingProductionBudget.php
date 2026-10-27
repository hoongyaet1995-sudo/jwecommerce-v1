<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingProductionBudget extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ceiling_microusd' => 'integer', 'reserved_microusd' => 'integer'];
    }
}
