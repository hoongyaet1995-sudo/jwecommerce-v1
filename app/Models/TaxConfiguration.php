<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_enabled',
        'is_published',
        'prices_include_tax',
        'address_basis',
        'merchant_compliance_metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_published' => 'boolean',
            'prices_include_tax' => 'boolean',
            'merchant_compliance_metadata' => 'array',
        ];
    }
}
