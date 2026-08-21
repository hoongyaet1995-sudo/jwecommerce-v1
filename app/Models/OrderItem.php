<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_variant_id', 'sku', 'title', 'variant_title', 'image_url', 'unit_price', 'quantity', 'line_total'];
}
