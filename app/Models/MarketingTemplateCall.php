<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketingTemplateCall extends Model {public $incrementing=false;protected $keyType='string';protected $guarded=[];protected function casts():array{return ['receipt'=>'array','reserved_microusd'=>'integer','actual_microusd'=>'integer'];}}
