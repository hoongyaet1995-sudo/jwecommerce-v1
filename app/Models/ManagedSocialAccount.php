<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagedSocialAccount extends Model
{
    protected $guarded=['id'];
    protected $hidden=['access_token','authorized_by'];
    protected function casts(): array
    {
        return ['access_token'=>'encrypted','permissions'=>'array','tasks'=>'array','checked_at'=>'datetime','inbound_enabled'=>'boolean','inbound_verified_at'=>'datetime','last_inbound_at'=>'datetime'];
    }
}
