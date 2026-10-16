<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPdpContent extends Model
{
    protected $fillable = [
        'highlights', 'specifications', 'use_store_default_shipping', 'shipping_policy',
        'custom_accordions', 'rating_score', 'review_count', 'configurator_label', 'story', 'faqs',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'story' => 'array',
            'faqs' => 'array',
            'specifications' => 'array',
            'use_store_default_shipping' => 'boolean',
            'shipping_policy' => 'array',
            'custom_accordions' => 'array',
            'rating_score' => 'decimal:1',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
