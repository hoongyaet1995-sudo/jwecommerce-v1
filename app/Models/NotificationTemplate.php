<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'status_name',
        'color',
        'email_enabled',
        'email_subject',
        'email_preheader',
        'email_heading',
        'email_body',
        'email_cta_text',
        'email_cta_url',
        'whatsapp_enabled',
        'whatsapp_message',
        'whatsapp_cta_buttons',
        'version',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_cta_buttons' => 'array',
            'version' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
