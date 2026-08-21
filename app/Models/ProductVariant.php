<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'title', 'color_name', 'color_hex', 'size', 'material', 'price', 'compare_at_price', 'stock', 'options', 'cost_price', 'weight_grams', 'barcode', 'image_url', 'enabled'];
    protected function casts(): array { return ['options' => 'array', 'enabled' => 'boolean']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
