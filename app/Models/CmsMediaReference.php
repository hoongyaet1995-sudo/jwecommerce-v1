<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMediaReference extends Model
{
    protected $fillable = [
        'asset_id',
        'page_document_id',
        'field_path',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'asset_id');
    }

    public function pageDocument(): BelongsTo
    {
        return $this->belongsTo(PageDocument::class);
    }
}
