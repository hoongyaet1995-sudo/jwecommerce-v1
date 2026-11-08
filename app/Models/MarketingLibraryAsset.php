<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingLibraryAsset extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['path', 'identity'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'seconds' => 'float', 'bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
