<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddressAmendment extends Model
{
    protected $fillable = [
        'order_id', 'order_address_id', 'before_snapshot', 'after_snapshot', 'reason_code',
        'internal_note', 'address_book_sync_requested', 'address_book_sync_result', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'address_book_sync_requested' => 'boolean',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderAddress(): BelongsTo { return $this->belongsTo(OrderAddress::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
