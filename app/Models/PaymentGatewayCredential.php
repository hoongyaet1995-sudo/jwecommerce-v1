<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayCredential extends Model
{
    protected $fillable = ['gateway_id', 'environment', 'credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array'];
    }

    public static function forGateway(string $gatewayId, string $environment): ?self
    {
        return static::query()
            ->where('gateway_id', $gatewayId)
            ->where('environment', $environment)
            ->first();
    }
}
