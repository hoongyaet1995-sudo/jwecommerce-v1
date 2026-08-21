<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'metadata',
        'is_published',
        'status',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PageDocument::class);
    }

    public function draftDocument(): HasOne
    {
        return $this->hasOne(PageDocument::class)
            ->where('stage', PageDocument::STAGE_DRAFT);
    }

    public function publishedDocument(): HasOne
    {
        return $this->hasOne(PageDocument::class)
            ->where('stage', PageDocument::STAGE_PUBLISHED);
    }
}
