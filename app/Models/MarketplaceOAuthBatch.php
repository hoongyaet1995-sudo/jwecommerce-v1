<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MarketplaceOAuthBatch extends Model
{
    protected $table='marketplace_oauth_batches';
    protected $guarded=[];
    protected $hidden=['seed_access_token','seed_refresh_token','application_id'];
    protected function casts(): array { return [
        'seed_access_token'=>'encrypted', 'seed_refresh_token'=>'encrypted',
        'seed_access_expires_at'=>'immutable_datetime','seed_refresh_expires_at'=>'immutable_datetime',
        'merchant_id_list'=>'array','completed_at'=>'immutable_datetime',
    ]; }
    public function stores() { return $this->hasMany(MarketplaceOAuthBatchStore::class,'batch_id'); }
}
