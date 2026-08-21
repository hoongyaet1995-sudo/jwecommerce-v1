<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;

class DashboardDataService
{
    /** @var list<string> */
    private const ACTIONABLE_ORDER_STATUSES = ['pending_payment', 'paid', 'processing'];

    /**
     * @return array{
     *     metrics: array{sales_today: int, orders: int, customers: int, low_stock: int, pending_orders: int},
     *     recentOrders: list<array{id: int, number: string, email: string, customerName: string, status: string, total: int, itemCount: int, createdAt: string}>,
     *     lowStockVariants: list<array{id: int, sku: string, title: string, stock: int, image: string, product: array{id: int, title: string, handle: string}|null}>,
     *     revenueTrend: list<array{label: string, total: int}>,
     *     categoryCounts: list<array{id: int, name: string, productsCount: int}>
     * }
     */
    public function data(): array
    {
        $paidStatuses = ['paid', 'processing', 'shipped', 'delivered'];

        $revenueTrend = collect(range(6, 0))->map(function (int $daysAgo) use ($paidStatuses): array {
            $date = today()->subDays($daysAgo);

            return [
                'label' => $date->format('D'),
                'total' => (int) Order::query()
                    ->whereDate('created_at', $date)
                    ->whereIn('status', $paidStatuses)
                    ->sum('total'),
            ];
        })->values()->all();

        $recentOrders = Order::query()->latest()->withCount('items')->take(5)->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'email' => $order->email,
                'customerName' => str($order->email)->before('@')->replace(['.', '-', '_'], ' ')->title()->toString(),
                'status' => $order->status,
                'total' => (int) $order->total,
                'itemCount' => (int) $order->items_count,
                'createdAt' => $order->created_at->toAtomString(),
            ])->values()->all();

        $lowStockVariants = ProductVariant::query()
            ->with([
                'product:id,title,handle',
                'product.images:id,product_id,path,position',
            ])
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->take(5)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'title' => $variant->title,
                'stock' => (int) $variant->stock,
                'image' => (string) $variant->product?->images->sortBy('position')->first()?->path,
                'product' => $variant->product === null ? null : [
                    'id' => $variant->product->id,
                    'title' => $variant->product->title,
                    'handle' => $variant->product->handle,
                ],
            ])->values()->all();

        $categoryCounts = Category::query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderByDesc('products_count')
            ->take(4)
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'productsCount' => (int) $category->products_count,
            ])->values()->all();

        return [
            'metrics' => [
                'sales_today' => (int) Order::query()->whereDate('created_at', today())->whereIn('status', $paidStatuses)->sum('total'),
                'orders' => Order::query()->count(),
                'customers' => Customer::query()->count(),
                'low_stock' => ProductVariant::query()->where('stock', '<=', 5)->count(),
                'pending_orders' => Order::query()->whereIn('status', self::ACTIONABLE_ORDER_STATUSES)->count(),
            ],
            'recentOrders' => $recentOrders,
            'lowStockVariants' => $lowStockVariants,
            'revenueTrend' => $revenueTrend,
            'categoryCounts' => $categoryCounts,
        ];
    }

    /**
     * @return array{metrics: array{sales_today: int, orders: int, customers: int, low_stock: int, pending_orders: int}, recentOrders: \Illuminate\Support\Collection<int, object>, lowStockVariants: \Illuminate\Support\Collection<int, object>, revenueTrend: \Illuminate\Support\Collection<int, array{label: string, total: int}>, categoryCounts: \Illuminate\Support\Collection<int, object>}
     */
    public function legacyViewData(): array
    {
        $data = $this->data();

        return [
            'metrics' => $data['metrics'],
            'recentOrders' => collect($data['recentOrders'])->map(fn (array $order): object => (object) $order),
            'lowStockVariants' => collect($data['lowStockVariants'])->map(function (array $variant): object {
                $variant['product'] = $variant['product'] === null ? null : (object) $variant['product'];

                return (object) $variant;
            }),
            'revenueTrend' => collect($data['revenueTrend']),
            'categoryCounts' => collect($data['categoryCounts'])->map(fn (array $category): object => (object) [
                'name' => $category['name'],
                'products_count' => $category['productsCount'],
            ]),
        ];
    }
}
