<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontShellSetting extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
