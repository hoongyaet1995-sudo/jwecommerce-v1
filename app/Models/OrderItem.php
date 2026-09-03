<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_variant_id', 'sku', 'title', 'variant_title', 'image_url', 'package_snapshot', 'unit_price', 'quantity', 'line_total'];

    protected function casts(): array
    {
        return ['package_snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $orderItem): void {
            if ($orderItem->isDirty('package_snapshot')) {
                throw new LogicException('An order item package snapshot is immutable.');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
