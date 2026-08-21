<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsMediaAsset extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'disk',
        'path',
        'url',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'checksum',
        'uploader_id',
        'orphaned_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'orphaned_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(CmsMediaReference::class, 'asset_id');
    }

    public function scopeCleanupEligible(Builder $query): Builder
    {
        return $query
            ->whereNotNull('orphaned_at')
            ->where('orphaned_at', '<=', now()->subDays(30))
            ->whereDoesntHave('references');
    }
}
