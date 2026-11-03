<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AddonDeal extends Model {
 protected $guarded=['id'];
 protected function casts():array{return ['enabled'=>'boolean','product_ids'=>'array','addons'=>'array','image_settings'=>'array','per_order_cap'=>'integer','starts_at'=>'datetime','ends_at'=>'datetime'];}
}
