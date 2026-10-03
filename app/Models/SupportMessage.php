<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportMessage extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['actions'=>'array']; }
    public function staff() { return $this->belongsTo(User::class, 'staff_user_id'); }
}
