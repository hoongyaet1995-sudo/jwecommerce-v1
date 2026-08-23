<?php

namespace App\Domain\Accounts;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\WishlistItem;

class OriginalAccountPayloadFactory
{
    /**
     * @return array{
     *     user: array<string, mixed>,
     *     orders: list<array<string, mixed>>,
     *     addresses: list<array<string, mixed>>,
     *     wishlist: list<array<string, mixed>>,
     *     dashboard: array<string, mixed>,
     *     gaps: list<string>
     * }
     */
    public function for(Customer $customer): array
    {
        $customer->loadMissing([
            'user',
            'orders.items.reviews',
            'orders.addresses',
            'addresses',
            'wishlistItems.product.images',
            'wishlistItems.product.variants',
        ]);

        return [
            'user' => $this->userPayload($customer),
            'orders' => $this->orderPayloads($customer),
            'addresses' => $this->addressPayloads($customer),
            'wishlist' => $this->wishlistPayloads($customer),
            'dashboard' => $this->dashboardPayload($customer),
            'gaps' => ['password', 'vouchers', 'two_factor', 'sessions', 'membership_points'],
        ];
    }

    /** @return array<string, mixed> */
    private function userPayload(Customer $customer): array
    {
        return [
            'id' => $customer->user->id,
            'name' => $customer->user->name,
            'email' => $customer->email,
            'firstName' => $customer->first_name,
            'lastName' => $customer->last_name,
            'phone' => $customer->phone,
            'avatar' => $customer->avatar,
            'memberSince' => $customer->created_at?->toDateString(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function orderPayloads(Customer $customer): array
    {
        return $customer->orders
            ->sortByDesc('created_at')
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'discountAmount' => $order->discount_amount,
                'shippingAmount' => $order->shipping_amount,
                'taxAmount' => $order->tax_amount,
                'total' => $order->total,
                'shippingMethod' => $order->shipping_method,
                'shippingLabel' => $order->shipping_label,
                'trackingCarrier' => $order->tracking_carrier,
                'trackingNumber' => $order->tracking_number,
                'createdAt' => $order->created_at?->toIso8601String(),
                'shippedAt' => $order->shipped_at?->toIso8601String(),
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'title' => $item->title,
                    'variantTitle' => $item->variant_title,
                    'imageUrl' => $item->image_url,
                    'unitPrice' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'lineTotal' => $item->line_total,
                    'reviews' => $item->reviews->map(fn ($review) => [
                        'id' => $review->id,
                        'status' => $review->status,
                        'attemptNumber' => $review->attempt_number,
                    ])->values()->all(),
                ])->values()->all(),
                'addresses' => $order->addresses->map(fn ($address) => [
                    'type' => $address->type,
                    'firstName' => $address->first_name,
                    'lastName' => $address->last_name,
                    'phone' => $address->phone,
                    'line1' => $address->line_1,
                    'line2' => $address->line_2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postalCode' => $address->postal_code,
                    'country' => $address->country,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function addressPayloads(Customer $customer): array
    {
        return $customer->addresses
            ->sortByDesc('is_default')
            ->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'type' => $address->type,
                'isDefault' => $address->is_default,
                'firstName' => $address->first_name,
                'lastName' => $address->last_name,
                'phone' => $address->phone,
                'line1' => $address->line_1,
                'line2' => $address->line_2,
                'city' => $address->city,
                'state' => $address->state,
                'postalCode' => $address->postal_code,
                'country' => $address->country,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function wishlistPayloads(Customer $customer): array
    {
        return $customer->wishlistItems
            ->filter(fn (WishlistItem $wishlistItem) => $wishlistItem->product !== null)
            ->map(fn (WishlistItem $wishlistItem) => [
                'id' => $wishlistItem->id,
                'product' => $this->productPayload($wishlistItem->product),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'handle' => $product->handle,
            'title' => $product->title,
            'brand' => $product->brand,
            'basePrice' => $product->base_price,
            'images' => $product->images->sortBy('position')->map(fn ($image) => [
                'path' => $image->path,
                'alt' => $image->alt,
                'position' => $image->position,
            ])->values()->all(),
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'title' => $variant->title,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'compareAtPrice' => $variant->compare_at_price,
                'imageUrl' => $variant->image_url,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function dashboardPayload(Customer $customer): array
    {
        $orders = $customer->orders->sortByDesc('created_at')->values();
        $activeOrders = $orders->filter(fn (Order $order) => ! in_array($order->status, ['cancelled', 'delivered', 'refunded'], true));

        return [
            'orderCount' => $orders->count(),
            'activeLogisticsCount' => $activeOrders->count(),
            'latestOrder' => $orders->isEmpty() ? null : $this->orderPayloadsForDashboard($orders->first()),
        ];
    }

    /** @return array<string, mixed> */
    private function orderPayloadsForDashboard(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'total' => $order->total,
            'currency' => $order->currency,
            'trackingCarrier' => $order->tracking_carrier,
            'trackingNumber' => $order->tracking_number,
            'createdAt' => $order->created_at?->toIso8601String(),
        ];
    }
}
