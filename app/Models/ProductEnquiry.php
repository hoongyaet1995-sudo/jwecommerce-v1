<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductEnquiry extends Model
{
    public function supportConversation(){return $this->belongsTo(SupportConversation::class,'support_conversation_id');}
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected function casts(): array {return ['product_snapshot'=>'array','version'=>'integer'];}
    public function events(){return $this->hasMany(ProductEnquiryEvent::class)->orderByDesc('id');}
    public function assignee(){return $this->belongsTo(User::class,'assigned_to');}
}
