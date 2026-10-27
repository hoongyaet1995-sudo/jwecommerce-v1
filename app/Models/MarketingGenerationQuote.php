<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class MarketingGenerationQuote extends Model {
    use HasUuids;
    protected $guarded=[];
    protected function casts():array {return ['payload'=>'array','pricing'=>'array','result'=>'array','included'=>'boolean','cap_microusd'=>'integer','max_posts'=>'integer','story_version'=>'integer','expires_at'=>'datetime','confirmed_at'=>'datetime'];}
}
