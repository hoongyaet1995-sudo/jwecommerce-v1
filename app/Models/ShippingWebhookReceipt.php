<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingWebhookReceipt extends Model
{
    protected $fillable = [
        'provider',
        'environment',
        'topic',
        'provider_uuid',
        'payload_hash',
        'shipment_id',
        'correlation_snapshot',
        'processing_state',
        'last_error',
        'attempt_count',
        'received_at',
        'queued_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'correlation_snapshot' => 'array',
            'received_at' => 'datetime',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
