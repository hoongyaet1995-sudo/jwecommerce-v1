<?php

namespace App\View\Composers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\View\View;

class AdminShellComposer
{
    public function compose(View $view): void
    {
        $notifications = collect()
            ->concat(Order::query()->latest()->limit(2)->get()->map(fn (Order $order) => [
                'title' => 'New order '.$order->number,
                'description' => $order->email.' · '.str_replace('_', ' ', $order->status),
                'time' => $order->created_at->diffForHumans(),
                'url' => route('admin.orders.index', ['search' => $order->number]),
                'icon' => 'Order',
            ]))
            ->concat(ProductVariant::query()->with('product')->where('stock', '<=', 3)->orderBy('stock')->limit(2)->get()->map(fn (ProductVariant $variant) => [
                'title' => 'Low stock: '.$variant->product->title,
                'description' => $variant->title.' · '.$variant->stock.' remaining',
                'time' => $variant->updated_at->diffForHumans(),
                'url' => route('admin.products.index', ['search' => $variant->product->title]),
                'icon' => 'Stock',
            ]))
            ->concat(Customer::query()->latest()->limit(1)->get()->map(fn (Customer $customer) => [
                'title' => 'New customer',
                'description' => trim($customer->first_name.' '.$customer->last_name) ?: $customer->email,
                'time' => $customer->created_at->diffForHumans(),
                'url' => route('admin.customers.index', ['search' => $customer->email]),
                'icon' => 'Customer',
            ]))
            ->take(5)
            ->values();

        $view->with('adminShellNotifications', $notifications);
    }
}
