<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingProviderAuthorizationTransaction extends Model
{
    protected $fillable = [
        'shipping_provider_id',
        'user_id',
        'state_hash',
        'session_fingerprint',
        'configuration_version',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'state_hash',
        'session_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'shipping_provider_id' => 'integer',
            'user_id' => 'integer',
            'configuration_version' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function shippingProvider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
