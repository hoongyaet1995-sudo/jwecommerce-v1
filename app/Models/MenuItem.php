<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MenuItem extends Model {
    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'label_cn', 'url', 'position', 'badge', 'badge_type',
        'featured_title', 'featured_description', 'featured_price', 'featured_image_url',
        'featured_cta_label', 'featured_cta_url',
        'link_type', 'target_id', 'icon', 'description', 'is_external', 'is_active',
        'is_highlighted', 'display_style',
    ];
    protected function casts(): array { return ['is_external' => 'boolean', 'is_active' => 'boolean', 'is_highlighted' => 'boolean']; }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('position'); }
}
