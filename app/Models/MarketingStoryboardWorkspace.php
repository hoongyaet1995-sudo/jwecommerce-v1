<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingStoryboardWorkspace extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scene_order' => 'array', 'profile' => 'array', 'last_confirmation' => 'array', 'version' => 'integer'];
    }
}
