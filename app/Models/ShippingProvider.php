<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShippingProvider extends Model
{
    protected $fillable = [
        'provider',
        'environment',
        'enabled',
        'connection_state',
        'configuration_version',
        'public_metadata',
        'last_verified_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'configuration_version' => 'integer',
            'public_metadata' => 'array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function credential(): HasOne
    {
        return $this->hasOne(ShippingProviderCredential::class);
    }
}
