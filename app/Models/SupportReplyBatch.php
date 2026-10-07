<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportReplyBatch extends Model
{
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected static function booted():void
    {
        static::saved(fn($batch)=>SupportConversation::whereKey($batch->conversation_id)->update(['updated_at'=>now()]));
    }
    protected function casts():array
    {
        return ['segments'=>'encrypted:array','actions'=>'encrypted:array','trace'=>'array','due_at'=>'datetime','deadline_at'=>'datetime','generation_started_at'=>'datetime',
            'first_source_id'=>'integer','latest_source_id'=>'integer','settings_version'=>'integer','control_version'=>'integer','next_index'=>'integer','interval_seconds'=>'integer'];
    }
}
