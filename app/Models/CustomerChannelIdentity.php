<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerChannelIdentity extends Model {
 protected $guarded=[];
 protected $hidden=['identity_hash','external_sender_id','metadata'];
 protected function casts():array{return ['metadata'=>'array','first_seen_at'=>'datetime','last_seen_at'=>'datetime','profile_checked_at'=>'datetime'];}
 public function customer(){return $this->belongsTo(Customer::class);}
}
