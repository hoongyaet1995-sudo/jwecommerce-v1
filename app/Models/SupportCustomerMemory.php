<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportCustomerMemory extends Model
{
    protected $guarded=[];
    protected $hidden=['content','identity_hash','trust_hash'];
    protected function casts():array{return ['content'=>'encrypted','expires_at'=>'datetime','revoked_at'=>'datetime'];}
    public function sourceConversation(){return $this->belongsTo(SupportConversation::class,'source_conversation_id');}
}
