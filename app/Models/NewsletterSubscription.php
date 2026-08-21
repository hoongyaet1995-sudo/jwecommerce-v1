<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscription extends Model
{
    protected $fillable = [
        'page_id',
        'page_id_snapshot',
        'coupon_id',
        'coupon_id_snapshot',
        'coupon_code_snapshot',
        'document_stage',
        'section_id',
        'section_type',
        'email',
    ];
}
