<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationAcceptance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function version()
    {
        return $this->belongsTo(EnquiryQuotationVersion::class, 'enquiry_quotation_version_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $row) {
            foreach (['enquiry_quotation_id', 'enquiry_quotation_version_id', 'accepted_at', 'accepted_by'] as $key) {
                if ($row->isDirty($key)) {
                    throw new \LogicException('Quotation acceptance is immutable.');
                }
            }if ($row->getOriginal('order_id') && $row->isDirty('order_id')) {
                throw new \LogicException('A quotation acceptance can only create one order.');
            }
        });
    }
}
