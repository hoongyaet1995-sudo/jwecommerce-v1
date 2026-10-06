<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversation extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['owner_hash','provider_sender_id'];
    protected function casts(): array { return ['last_message_at' => 'datetime']; }
    public function messages() { return $this->hasMany(SupportMessage::class, 'conversation_id'); }
    public function notes() { return $this->hasMany(SupportStaffNote::class, 'conversation_id'); }
    public function customerUser() { return $this->belongsTo(User::class, 'customer_user_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
