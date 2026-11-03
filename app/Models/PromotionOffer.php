<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class PromotionOffer extends Model {
 protected $fillable=['cover_image','image_settings','name','description','quantity','price','repeat','enabled','starts_at','ends_at'];
 protected function casts():array{return ['image_settings'=>'array','quantity'=>'integer','price'=>'integer','repeat'=>'boolean','enabled'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime'];}
 public function products():BelongsToMany{return $this->belongsToMany(Product::class, 'promotion_offer_product');}
}
