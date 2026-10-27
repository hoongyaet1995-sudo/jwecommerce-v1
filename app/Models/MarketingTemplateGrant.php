<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Model,Concerns\HasUuids};
class MarketingTemplateGrant extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['active'=>'boolean','policy'=>'array','pricing'=>'array','expires_at'=>'datetime','cap_microusd'=>'integer','max_llm'=>'integer','max_tts'=>'integer'];}}
