<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class MarketingChannelConnection extends Model {
 use HasUuids;
 protected $guarded=['id'];
 protected $hidden=['access_token','authorized_by','app_id','merchant_key'];
 protected function casts():array{return ['access_token'=>'encrypted','permissions'=>'array','tasks'=>'array','checked_at'=>'datetime','version'=>'integer'];}
}
