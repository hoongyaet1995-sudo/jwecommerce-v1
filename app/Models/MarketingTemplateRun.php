<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Model,Concerns\HasUuids};
class MarketingTemplateRun extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['input'=>'array','draft'=>'array','checks'=>'array','quote'=>'array','result'=>'array','error'=>'array','corrections'=>'integer'];}}
