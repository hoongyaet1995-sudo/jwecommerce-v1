<?php
namespace App\Models;
use Illuminate\Database\Eloquent\{Model,Concerns\HasUuids};
class MarketingContentTemplate extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['document'=>'array','version'=>'integer','archived_at'=>'datetime'];}}
