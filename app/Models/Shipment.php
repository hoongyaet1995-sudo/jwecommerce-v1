<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $hidden = [
        'label_path',
        'tracking_url',
        'tracking_polled_at',
    ];

    protected $fillable = [
        'order_id', 'sequence', 'source', 'carrier_code', 'carrier_name', 'service_code', 'service_name',
        'tracking_number', 'status', 'shipping_cost', 'currency', 'label_path', 'provider_reference',
        'provider_order_reference', 'tracking_url', 'shipment_quote_id', 'quote_snapshot', 'package_snapshot',
        'dispatched_at', 'delivered_at', 'cancelled_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'quote_snapshot' => 'array',
            'package_snapshot' => 'array',
            'tracking_polled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(ShipmentQuote::class, 'shipment_quote_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
