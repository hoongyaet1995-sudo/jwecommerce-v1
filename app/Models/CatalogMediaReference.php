<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogMediaReference extends Model
{
    protected $fillable = [
        'asset_id',
        'owner_type',
        'owner_id',
        'field_path',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'asset_id');
    }
}
