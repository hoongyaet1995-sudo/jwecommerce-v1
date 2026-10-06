<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerDirectoryDataService
{
    /** @var list<string> */
    private const PAID_LIFECYCLE_STATUSES = ['paid', 'processing', 'shipped', 'delivered'];

    /**
     * The customer-directory query is shared with the retained legacy controller so
     * lifetime spend keeps the same paid-order definition in every admin surface.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    public function customers(?string $search = null, ?string $platform = null, ?string $tag = null): LengthAwarePaginator
    {
        return Customer::query()
            ->whereNull('merged_into_id')
            ->with('platformPresences')->withCount('orders')
            ->withSum([
                'orders as paid_spend' => fn ($orders) => $orders->whereIn('status', self::PAID_LIFECYCLE_STATUSES),
            ], 'total')
            ->with([
                'addresses' => fn ($addresses) => $addresses
                    ->orderByDesc('is_default')
                    ->orderBy('id'),
                'orders' => fn ($orders) => $orders
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->when($search, fn ($query, $value) => $query->where(function ($customers) use ($value): void {
                $customers
                    ->where('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
            }))
            ->when($platform, fn($q) => $q->whereHas('platformPresences', fn($p) => $p->where('platform', $platform)))
            ->when($tag, fn($q) => $q->whereJsonContains('tags', $tag))
            ->latest()
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * @return array{
     *     customers: list<array{id: int, name: string, email: string, phone: string|null, avatar: null, totalOrders: int, totalSpent: int, currency: string|null, registeredAt: string, lastOrderAt: string|null, status: null, tags: list<never>, notes: null, addresses: list<array{id: int, isDefault: bool, label: string|null, firstName: string, lastName: string, phone: string|null, address1: string, address2: string|null, city: string, province: string|null, country: string, postalCode: string}>}>,
     *     pagination: array{currentPage: int, lastPage: int, perPage: int, total: int}
     * }
     */
    public function data(?string $search = null, ?string $platform = null, ?string $tag = null): array
    {
        $customers = $this->customers($search, $platform, $tag);

        return [
            'customers' => $customers->getCollection()->map(fn (Customer $customer): array => $this->customerData($customer))->values()->all(),
            'pagination' => [
                'currentPage' => $customers->currentPage(),
                'lastPage' => $customers->lastPage(),
                'perPage' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ];
    }

    public function findData(Customer $customer): array
    {
        $customer = Customer::query()->with('platformPresences')->withCount('orders')->withSum([
            'orders as paid_spend' => fn ($orders) => $orders->whereIn('status', self::PAID_LIFECYCLE_STATUSES),
        ], 'total')->with(['addresses' => fn ($query) => $query->orderByDesc('is_default')->orderBy('id'), 'orders' => fn ($query) => $query->latest()->limit(1)])->findOrFail($customer->id);

        return $this->customerData($customer);
    }

    private function customerData(Customer $customer): array
    {
        /** @var Order|null $lastOrder */
        $lastOrder = $customer->orders->first();
        $name = collect([$customer->first_name, $customer->last_name])->filter(fn (?string $part): bool => filled($part))->join(' ');
        return [
                    'id' => $customer->id,
                    // The email remains the truthful display identifier for guest
                    // checkout records that have no persisted customer name.
                    'name' => $name !== '' ? $name : ($customer->email ?? 'Customer'),
                    'platforms' => $customer->platformPresences->pluck('platform')->unique()->values()->all(),
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'avatar' => $customer->avatar,
                    'totalOrders' => (int) $customer->orders_count,
                    'totalSpent' => (int) ($customer->paid_spend ?? 0),
                    'currency' => $lastOrder?->currency,
                    'registeredAt' => $customer->created_at->toAtomString(),
                    'lastOrderAt' => $lastOrder?->created_at?->toAtomString(),
                    'status' => $customer->status,
                    'tags' => $customer->tags ?? [],
                    'notes' => $customer->notes,
                    'addresses' => $customer->addresses->map(fn ($address): array => [
                        'id' => $address->id,
                        'isDefault' => (bool) $address->is_default,
                        'label' => $address->label,
                        'type' => $address->type,
                        'firstName' => $address->first_name,
                        'lastName' => $address->last_name,
                        'phone' => $address->phone,
                        'address1' => $address->line_1,
                        'address2' => $address->line_2,
                        'city' => $address->city,
                        'province' => $address->state,
                        'country' => $address->country,
                        'postalCode' => $address->postal_code,
                    ])->values()->all(),
                ];
    }
}
