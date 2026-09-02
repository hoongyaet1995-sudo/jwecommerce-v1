<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderAddress extends Model
{
    protected $fillable = ['order_id', 'type', 'first_name', 'last_name', 'phone', 'line_1', 'line_2', 'city', 'state', 'postal_code', 'country'];

    public function amendments(): HasMany
    {
        return $this->hasMany(OrderAddressAmendment::class)->latest('id');
    }
}
