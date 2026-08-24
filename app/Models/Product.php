<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = ['category_id', 'handle', 'title', 'subtitle', 'brand', 'description', 'base_price', 'compare_at_price', 'features', 'status', 'is_featured', 'has_variants', 'options', 'tags', 'seo_title', 'seo_description'];
    protected function casts(): array { return ['features' => 'array', 'options' => 'array', 'tags' => 'array', 'is_featured' => 'boolean', 'has_variants' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function collections(): BelongsToMany { return $this->belongsToMany(Collection::class); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class); }
    public function pdpContent(): HasOne { return $this->hasOne(ProductPdpContent::class); }
    public function pdpReviews(): HasMany { return $this->hasMany(ProductPdpReview::class)->orderBy('sort_order'); }
    public function reviews(): HasMany { return $this->hasMany(ProductReview::class); }
    public function pdpRelatedProducts(): HasMany { return $this->hasMany(ProductPdpRelatedProduct::class)->orderBy('sort_order'); }

}
