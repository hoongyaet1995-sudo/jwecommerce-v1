<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPdpReview extends Model
{
    protected $fillable = [
        'author', 'rating', 'review_date', 'title', 'comment', 'verified_buyer',
        'variant_purchased', 'helpful_count', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['verified_buyer' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
