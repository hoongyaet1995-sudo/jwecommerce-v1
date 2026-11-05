<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefund extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            // Capture the first terminal event, never infer it from later edits.
            if ($model->status === 'completed' && (!$model->exists || $model->isDirty('status')) && !$model->completed_at) {
                $model->completed_at = now();
            }
            if ($model->exists && $model->getRawOriginal('completed_at')) {
                $model->completed_at = $model->getRawOriginal('completed_at');
            }
        });

        static::saved(function (self $refund): void {
            if ($refund->status === 'completed' && ($order = Order::find($refund->order_id))) {
                app(\App\Services\Rewards\RewardsWallet::class)->synchronize($order);
            }
        });
    }

    protected $fillable = ['order_id', 'order_payment_id', 'user_id', 'amount', 'reason', 'status'];
}
