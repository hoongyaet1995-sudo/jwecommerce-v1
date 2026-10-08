<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportFaqDraft extends Model
{
    public function sourceMessage()
    {
        return $this->belongsTo(SupportMessage::class, 'source_message_id');
    }

    public function staffMessage()
    {
        return $this->belongsTo(SupportMessage::class, 'staff_message_id');
    }

    public function conversation()
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['version' => 'integer', 'reviewed_at' => 'datetime'];
    }
}
