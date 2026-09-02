<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCorrection extends Model
{
    protected $fillable = [
        'order_id', 'source_shipment_id', 'replacement_shipment_id', 'active_order_id',
        'reason_code', 'internal_note', 'original_snapshot', 'external_action_state',
        'external_action_note', 'status', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['original_snapshot' => 'array', 'completed_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function activeOrder(): BelongsTo { return $this->belongsTo(Order::class, 'active_order_id'); }
    public function sourceShipment(): BelongsTo { return $this->belongsTo(Shipment::class, 'source_shipment_id'); }
    public function replacementShipment(): BelongsTo { return $this->belongsTo(Shipment::class, 'replacement_shipment_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
