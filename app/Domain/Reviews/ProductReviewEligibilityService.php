<?php

namespace App\Domain\Reviews;

use App\Models\Customer;
use App\Models\OrderItem;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductReviewEligibilityService
{
    public function assertCanSubmit(Customer $customer, OrderItem $item): void
    {
        $order = $item->order;
        if ($order === null || $order->customer_id !== $customer->id) {
            $this->deny('This order item does not belong to the authenticated customer.', 403);
        }
        if ($order->status !== 'delivered') {
            $this->deny('Only delivered order items may be reviewed.', 422);
        }
        if ($item->reviews()->active()->exists()) {
            $this->deny('This order item already has a review awaiting approval or published.', 409);
        }
    }

    private function deny(string $message, int $status): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
