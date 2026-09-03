<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $fillable = ['number', 'order_id', 'order_item_id', 'customer_id', 'replacement_order_id', 'resolved_refund_id', 'external_refund_reference', 'manual_refund_note', 'status', 'resolution', 'quantity', 'reason', 'details', 'eligible_until', 'policy_window_days', 'policy_summary_snapshot', 'received_quantity', 'condition', 'warehouse_note', 'rejection_reason', 'approved_at', 'received_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['eligible_until' => 'datetime', 'approved_at' => 'datetime', 'received_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function replacementOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }

    public function resolvedRefund(): BelongsTo
    {
        return $this->belongsTo(OrderRefund::class, 'resolved_refund_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReturnRequestEvidence::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnRequestEvent::class);
    }
}
