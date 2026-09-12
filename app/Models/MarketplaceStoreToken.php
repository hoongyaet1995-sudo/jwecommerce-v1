<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceStoreToken extends Model
{
    protected $fillable = [
        'credential_source', 'test_lease_expires_at', 'store_id', 'access_token', 'refresh_token', 'application_id',
        'access_expires_at', 'refresh_expires_at',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'application_id'];

    protected function casts(): array
    {
        return [
            'test_lease_expires_at' => 'immutable_datetime',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_expires_at' => 'immutable_datetime',
            'refresh_expires_at' => 'immutable_datetime',
        ];
    }
}
