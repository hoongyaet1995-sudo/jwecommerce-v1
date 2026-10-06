<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportAttachment extends Model {
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected $hidden=['owner_hash','provider_reference','path'];
    protected function casts(): array { return ['provider_reference'=>'encrypted']; }
    public function conversation() { return $this->belongsTo(SupportConversation::class); }
}
