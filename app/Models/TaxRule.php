<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_class_id',
        'tax_zone_id',
        'label',
        'tax_type',
        'rate_percent',
        'applies_to_shipping',
        'priority',
        'is_enabled',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'applies_to_shipping' => 'boolean',
            'is_enabled' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class);
    }

    public function orderTaxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }

    public function matchesAddress(array $address): bool
    {
        $zone = $this->taxZone;

        if (! $zone || strcasecmp($zone->country, (string) ($address['country'] ?? '')) !== 0) {
            return false;
        }

        if ($zone->state !== null && strcasecmp($zone->state, (string) ($address['state'] ?? '')) !== 0) {
            return false;
        }

        return $zone->postcode === null
            || $zone->postcode === ($address['postcode'] ?? $address['postal_code'] ?? null);
    }

    public function isEffectiveAt(CarbonInterface $at): bool
    {
        return ($this->effective_from === null || $this->effective_from->lessThanOrEqualTo($at))
            && ($this->effective_until === null || $this->effective_until->greaterThanOrEqualTo($at));
    }

}
