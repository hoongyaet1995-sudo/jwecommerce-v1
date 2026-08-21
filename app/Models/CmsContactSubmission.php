<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsContactSubmission extends Model
{
    protected $fillable = [
        'page_id',
        'page_id_snapshot',
        'document_stage',
        'section_id',
        'section_type',
        'name',
        'email',
        'subject',
        'category',
        'message',
    ];
}
