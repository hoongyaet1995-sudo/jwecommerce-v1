<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportMessage extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['actions'=>'array','ai_trace'=>'array']; }
    public function conversation() { return $this->belongsTo(SupportConversation::class); }
    public function attachments() { return $this->hasMany(SupportAttachment::class, 'message_id'); }
    public function staff() { return $this->belongsTo(User::class, 'staff_user_id'); }
}
