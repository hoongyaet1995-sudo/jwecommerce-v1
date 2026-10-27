<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingConversationMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['proposal' => 'array', 'context'=>'array', 'base_version' => 'integer', 'sequence' => 'integer'];
    }
}
