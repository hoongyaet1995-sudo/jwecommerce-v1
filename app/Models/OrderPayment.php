<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'proof_uploaded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'configuration_snapshot' => 'array',
            'expires_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
        ];
    }
}
