<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTaxLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tax_rule_id',
        'tax_class_id',
        'label',
        'tax_type',
        'rate_percent',
        'taxable_amount',
        'tax_amount',
        'configuration_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'configuration_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }
}
