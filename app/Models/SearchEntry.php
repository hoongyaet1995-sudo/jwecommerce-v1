<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['topics' => 'array', 'excluded' => 'boolean', 'title_locked' => 'boolean', 'description_locked' => 'boolean', 'version' => 'integer'];
    }
}
