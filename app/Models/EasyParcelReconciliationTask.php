<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EasyParcelReconciliationTask extends Model
{
    protected $table = 'easyparcel_reconciliation_tasks';

    protected $fillable = [
        'provider',
        'environment',
        'task_key',
        'type',
        'shipment_quote_id',
        'shipment_id',
        'shipping_webhook_receipt_id',
        'application_reference',
        'state',
        'available_at',
        'published_at',
        'publication_attempt_count',
        'processing_attempt_count',
        'lease_token',
        'lease_expires_at',
        'last_error',
        'completed_at',
        'alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'published_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'alerted_at' => 'datetime',
            'publication_attempt_count' => 'integer',
            'processing_attempt_count' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(ShipmentQuote::class, 'shipment_quote_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(ShippingWebhookReceipt::class, 'shipping_webhook_receipt_id');
    }
}
