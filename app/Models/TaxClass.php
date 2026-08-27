<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxClass extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'rate_percent', 'is_active', 'active_slot'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_percent' => 'decimal:4',
            'active_slot' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class);
    }

    public function orderTaxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }
}
