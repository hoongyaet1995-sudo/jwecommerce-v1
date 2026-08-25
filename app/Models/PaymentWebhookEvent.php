<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'order_payment_id',
        'provider',
        'provider_event_id',
        'environment',
        'raw_payload',
        'normalized_payload',
        'verified_at',
    ];

    protected function casts(): array
    {
        return ['normalized_payload' => 'array', 'verified_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }
}
