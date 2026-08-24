<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReview extends Model
{
    protected $fillable = [
        'order_item_id', 'product_id', 'customer_id', 'attempt_number', 'status', 'active_lock',
        'rating', 'title', 'comment', 'order_number_snapshot', 'product_title_snapshot',
        'variant_title_snapshot', 'customer_name_snapshot', 'moderation_reason', 'moderated_by',
        'submitted_at', 'published_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder { return $query->whereIn('status', ['pending', 'published']); }
    public function scopePublished(Builder $query): Builder { return $query->where('status', 'published'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function moderator(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }
    public function media(): HasMany { return $this->hasMany(ProductReviewMedia::class)->orderBy('position'); }
    public function events(): HasMany { return $this->hasMany(ProductReviewEvent::class)->orderBy('occurred_at'); }
}
