<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnquiryQuotation extends Model
{
    protected $attributes = ['status' => 'draft', 'revision' => 1, 'lock_version' => 1];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['document' => 'array', 'revision' => 'integer', 'lock_version' => 'integer'];
    }

    public function enquiry()
    {
        return $this->belongsTo(ProductEnquiry::class, 'product_enquiry_id');
    }

    public function versions()
    {
        return $this->hasMany(EnquiryQuotationVersion::class)->orderByDesc('revision');
    }
}
