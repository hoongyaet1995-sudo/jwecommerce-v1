<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    protected $fillable = ['slug', 'title', 'subtitle', 'description', 'cover_image', 'badge', 'type', 'match_type', 'dynamic_rules', 'status', 'is_active'];
    protected function casts(): array { return ['dynamic_rules' => 'array', 'is_active' => 'boolean']; }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class); }
}
