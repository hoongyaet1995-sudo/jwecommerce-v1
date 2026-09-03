<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestEvidence extends Model
{
    protected $table = 'return_request_evidence';

    protected $fillable = ['return_request_id', 'path', 'original_name', 'mime_type', 'size'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}
