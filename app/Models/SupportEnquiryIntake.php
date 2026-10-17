<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportEnquiryIntake extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'unresolved' => 'array', 'answered' => 'array', 'asked_fields' => 'array', 'ai_suspended' => 'boolean'];
    }
}
