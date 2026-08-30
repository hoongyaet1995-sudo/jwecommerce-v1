<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EasyParcelShipmentRecovery extends Model
{
    protected $table = 'easyparcel_shipment_recoveries';

    protected $fillable = [
        'shipment_id',
        'order_id',
        'confirmed_by',
        'state',
        'reason',
        'provider_cancel_state',
        'provider_cancel_message',
        'replacement_shipment_id',
        'confirmed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function replacementShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'replacement_shipment_id');
    }
}
