<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestEvent extends Model
{
    protected $fillable = ['return_request_id', 'event_type', 'actor_type', 'actor_id', 'note', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
