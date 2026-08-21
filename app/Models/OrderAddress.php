<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddress extends Model
{
    protected $fillable = ['order_id', 'type', 'first_name', 'last_name', 'phone', 'line_1', 'line_2', 'city', 'state', 'postal_code', 'country'];
}
