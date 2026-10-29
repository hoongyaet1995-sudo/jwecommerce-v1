<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Model,Concerns\HasUuids};
class MarketingCampaign extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['version'=>'integer'];}}
