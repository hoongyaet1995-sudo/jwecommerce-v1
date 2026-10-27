<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingStoryboardProviderCall extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['input' => 'array', 'result' => 'array', 'cap_microusd' => 'integer', 'actual_microusd' => 'integer'];
    }
}
