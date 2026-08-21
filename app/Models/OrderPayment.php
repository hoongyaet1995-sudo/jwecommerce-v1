<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    protected $fillable = ['order_id', 'gateway', 'status', 'reference', 'payload'];
    protected function casts(): array { return ['payload' => 'array']; }
}
