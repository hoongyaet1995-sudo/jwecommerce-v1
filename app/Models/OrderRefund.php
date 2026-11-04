<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefund extends Model
{
    protected static function booted(): void
    {
        static::saved(function (self $refund): void {
            if ($refund->status === 'completed' && ($order = Order::find($refund->order_id))) {
                app(\App\Services\Rewards\RewardsWallet::class)->synchronize($order);
            }
        });
    }

    protected $fillable = ['order_id', 'order_payment_id', 'user_id', 'amount', 'reason', 'status'];
}
