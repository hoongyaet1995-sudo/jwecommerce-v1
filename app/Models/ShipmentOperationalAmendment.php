<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentOperationalAmendment extends Model
{
    protected $fillable = [
        'shipment_id', 'before_snapshot', 'after_snapshot', 'reason_code', 'internal_note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array'];
    }

    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
