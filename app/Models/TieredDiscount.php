<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TieredDiscount extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['enabled'=>'boolean','product_ids'=>'array','tiers'=>'array','starts_at'=>'datetime','ends_at'=>'datetime'];}
}
