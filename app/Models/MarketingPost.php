<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Model,Concerns\HasUuids};
class MarketingPost extends Model {
 use HasUuids;
 protected $guarded=[];
 protected function casts():array{return ['document'=>'array','version'=>'integer','scheduled_at'=>'immutable_datetime'];}
}
