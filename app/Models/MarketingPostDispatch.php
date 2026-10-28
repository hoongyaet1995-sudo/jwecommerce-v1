<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Model,Concerns\HasUuids};
class MarketingPostDispatch extends Model {
 use HasUuids;
 protected $guarded=[];
 protected function casts():array{return ['payload'=>'array','due_at'=>'immutable_datetime','next_check_at'=>'immutable_datetime','revision'=>'integer'];}
}
