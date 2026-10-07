<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAttachmentAnalysis extends Model
{
    protected $guarded = [];
    protected $hidden = ['result','attempt_key'];
    protected function casts(): array
    {
        return ['result'=>'encrypted:array','started_at'=>'datetime','completed_at'=>'datetime','expires_at'=>'datetime'];
    }
    public function attachment() { return $this->belongsTo(SupportAttachment::class); }
    public function source() { return $this->belongsTo(SupportMessage::class,'source_message_id'); }
}
