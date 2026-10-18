<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchTask extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['facts' => 'array', 'before' => 'array', 'result' => 'array', 'usage' => 'array', 'replace_legacy' => 'boolean', 'available_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
