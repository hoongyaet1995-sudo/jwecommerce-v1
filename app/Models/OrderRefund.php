<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefund extends Model
{
    protected $fillable = ['order_id', 'order_payment_id', 'user_id', 'amount', 'reason', 'status'];
}
