<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'title', 'color_name', 'color_hex', 'size', 'material', 'price', 'compare_at_price', 'stock', 'options', 'cost_price', 'weight_grams', 'package_length_cm', 'package_width_cm', 'package_height_cm', 'barcode', 'image_url', 'visual_type', 'color_code', 'option_image', 'enabled'];
    protected function casts(): array { return ['options' => 'array', 'package_length_cm' => 'float', 'package_width_cm' => 'float', 'package_height_cm' => 'float', 'enabled' => 'boolean', 'inventory_initialized_at' => 'datetime', 'first_physical_verified_at' => 'datetime']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
