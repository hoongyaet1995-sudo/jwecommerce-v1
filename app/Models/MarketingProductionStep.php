<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class MarketingProductionStep extends Model {
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['depends_on'=>'array','result'=>'array','attempts'=>'integer'];}
}
