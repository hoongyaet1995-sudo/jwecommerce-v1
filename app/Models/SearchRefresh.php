<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchRefresh extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['use_ai' => 'boolean', 'total' => 'integer', 'skipped' => 'integer'];
    }
}
