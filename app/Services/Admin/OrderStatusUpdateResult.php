<?php

namespace App\Services\Admin;

use App\Models\NotificationDispatch;
use App\Models\Order;

final readonly class OrderStatusUpdateResult
{
    public function __construct(
        public Order $order,
        public ?NotificationDispatch $notificationDispatch,
    ) {}
}
