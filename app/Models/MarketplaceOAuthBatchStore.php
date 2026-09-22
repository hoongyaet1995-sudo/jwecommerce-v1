<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MarketplaceOAuthBatchStore extends Model
{
    protected $table='marketplace_oauth_batch_stores';
    protected $guarded=[];
    protected function casts(): array { return ['last_attempt_at'=>'immutable_datetime']; }
    public function batch() { return $this->belongsTo(MarketplaceOAuthBatch::class,'batch_id'); }
}
