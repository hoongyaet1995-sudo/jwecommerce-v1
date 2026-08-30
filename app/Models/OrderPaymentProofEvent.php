<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentProofEvent extends Model
{
    protected $fillable = [
        'order_id',
        'order_payment_id',
        'user_id',
        'event_type',
        'reason',
        'proof_sha256',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
