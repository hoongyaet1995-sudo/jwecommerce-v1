<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'provider',
        'environment',
        'status',
        'reference',
        'idempotency_key',
        'amount',
        'currency',
        'payload',
        'configuration_snapshot',
        'proof_path',
        'proof_disk',
        'proof_original_name',
        'proof_mime_type',
        'proof_size',
        'proof_sha256',
        'proof_status',
        'proof_uploaded_at',
        'proof_reviewed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'configuration_snapshot' => 'array',
            'expires_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
            'proof_reviewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function proofEvents(): HasMany
    {
        return $this->hasMany(OrderPaymentProofEvent::class)->latest('id');
    }
}
