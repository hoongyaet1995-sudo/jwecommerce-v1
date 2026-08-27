<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShipmentQuote extends Model
{
    protected $hidden = [
        'request_snapshot',
        'response_snapshot',
        'idempotency_reference',
        'booking_claim_key',
    ];

    protected $fillable = [
        'order_id',
        'shipping_provider_id',
        'provider',
        'environment',
        'provider_configuration_version',
        'package_snapshot',
        'request_snapshot',
        'response_snapshot',
        'selected_service_code',
        'idempotency_reference',
        'booking_state',
        'booking_claim_key',
        'last_error',
        'expires_at',
        'booking_started_at',
        'booked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'provider_configuration_version' => 'integer',
            'package_snapshot' => 'array',
            'request_snapshot' => 'array',
            'response_snapshot' => 'array',
            'expires_at' => 'datetime',
            'booking_started_at' => 'datetime',
            'booked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingProvider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
