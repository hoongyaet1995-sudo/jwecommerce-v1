<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingAvatar extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['draft' => 'array', 'current_version' => 'integer'];
    }
}
