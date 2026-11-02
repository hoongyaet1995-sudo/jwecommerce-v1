<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    protected $fillable = ['image_settings', 'seo_title', 'seo_description', 'slug', 'title', 'subtitle', 'description', 'cover_image', 'badge', 'type', 'match_type', 'dynamic_rules', 'status', 'is_active'];
    protected function casts(): array { return ['image_settings' => 'array', 'dynamic_rules' => 'array', 'is_active' => 'boolean']; }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class); }
}
