<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = ['code', 'name', 'description', 'discount_type', 'value', 'min_spend', 'usage_limit', 'usage_count', 'per_customer_limit', 'is_active', 'starts_at', 'ends_at'];
    protected function casts(): array { return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function scopeCurrentlyValid(Builder $query): Builder { return $query->where('is_active', true)->where(fn (Builder $nested) => $nested->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn (Builder $nested) => $nested->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->where(fn (Builder $nested) => $nested->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit')); }
    public function isCurrentlyValid(): bool { return $this->is_active && (! $this->starts_at || $this->starts_at->lte(now())) && (! $this->ends_at || $this->ends_at->gte(now())) && ($this->usage_limit === null || $this->usage_count < $this->usage_limit); }
    public function usages(): HasMany { return $this->hasMany(CouponUsage::class); }
}
