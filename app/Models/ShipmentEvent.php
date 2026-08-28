<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    protected $hidden = ['external_event_id'];

    protected $fillable = ['shipment_id', 'status', 'occurred_at', 'location', 'description', 'source', 'external_event_id', 'created_by'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
