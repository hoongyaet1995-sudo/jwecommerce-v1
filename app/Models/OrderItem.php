<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_variant_id', 'sku', 'title', 'variant_title', 'image_url', 'package_snapshot', 'unit_price', 'quantity', 'line_total'];
    protected function casts(): array { return ['package_snapshot' => 'array']; }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function productVariant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function reviews(): HasMany { return $this->hasMany(ProductReview::class); }
}
