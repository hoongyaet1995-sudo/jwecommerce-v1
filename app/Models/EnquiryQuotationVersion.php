<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnquiryQuotationVersion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['document' => 'array', 'revision' => 'integer', 'sent_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function quotation()
    {
        return $this->belongsTo(EnquiryQuotation::class, 'enquiry_quotation_id');
    }

    protected static function booted(): void
    {
        static::updating(function ($row) {
            foreach (['document', 'revision', 'enquiry_quotation_id', 'sent_at', 'expires_at', 'sent_by'] as $key) {
                if ($row->isDirty($key)) {
                    throw new \LogicException('Sent quotation versions are immutable.');
                }
            }
        });
    }
}
