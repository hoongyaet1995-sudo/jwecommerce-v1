<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConnection extends Model
{
    protected $primaryKey = 'provider';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['api_key'];

    public function originalIsEquivalent($key)
    {
        try {
            return parent::originalIsEquivalent($key);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $error) {
            if ($key !== 'api_key') {
                throw $error;
            }

            // A replacement ciphertext must be saved even if the old key is unreadable.
            return false;
        }
    }

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted', 'models' => 'array',
            'enabled' => 'boolean', 'support_enabled' => 'boolean', 'builder_enabled' => 'boolean',
            'verified_at' => 'datetime', 'text_tested_at' => 'datetime', 'version' => 'integer',
        ];
    }
}
