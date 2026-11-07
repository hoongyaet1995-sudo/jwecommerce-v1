<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketingPostAutomation extends Model {
 public $incrementing=false; protected $keyType='string'; protected $guarded=[];
 protected function casts():array{return ['settings'=>'array','version'=>'integer','schedule_enabled'=>'boolean'];}
}
