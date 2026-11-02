<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['image_settings', 'parent_id', 'slug', 'name', 'name_cn', 'image_url', 'description', 'icon', 'seo_title', 'seo_description', 'seo_keywords', 'sort_order', 'is_active'];
    protected function casts(): array { return ['image_settings' => 'array', 'is_active' => 'boolean']; }
    public function products(): HasMany { return $this->hasMany(Product::class); }

}
