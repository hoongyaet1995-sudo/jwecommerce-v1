<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketingDailyDraftRun extends Model {
 public $incrementing=false;protected $keyType='string';protected $guarded=[];
 protected function casts(): array {return ['snapshot'=>'array','creative'=>'array','receipts'=>'array','post_ids'=>'array'];}
}
