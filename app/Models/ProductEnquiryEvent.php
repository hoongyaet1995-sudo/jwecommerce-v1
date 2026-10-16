<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductEnquiryEvent extends Model
{
    protected $guarded=[];
    protected function casts(): array {return ['changes'=>'array'];}
    public function actor(){return $this->belongsTo(User::class,'actor_id');}
}
