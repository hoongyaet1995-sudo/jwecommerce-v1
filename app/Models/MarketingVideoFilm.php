<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class MarketingVideoFilm extends Model {
 use HasUuids;protected $guarded=[];
 protected function casts():array{return ['input'=>'array','result'=>'array','approved_at'=>'datetime'];}
}
