<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketingAutomationRun extends Model {
 public $incrementing=false; protected $keyType='string'; protected $guarded=[];
 protected function casts():array{return ['snapshot'=>'array','selection'=>'array','receipt'=>'array'];}
}
