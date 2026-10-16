<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\OrderTaxLine;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryMutationService;
use App\Services\Inventory\ManagedInventoryActivation;
use App\Services\Inventory\OrderAllocationService;
use App\Services\Inventory\StockAllocationService;
use App\Services\Inventory\StoreManagement\OrderInventoryRouter;
use App\Services\Inventory\WebsiteInventoryAvailability;
use App\Services\Inventory\WebsiteCurrentStockOrderEffects;
use App\Services\Notifications\OrderNotificationMilestoneService;
use App\Services\Payments\PaymentProofService;
use App\Services\Shipping\EasyParcelBookingGuard;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class CheckoutService
{
    private const TRANSITIONS = [
        'pending_payment' => ['paid', 'cancelled'],
        'paid' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly PaymentMethodAvailabilityService $paymentMethods,
        private readonly PaymentAttemptService $paymentAttempts,
        private readonly CartPricingService $pricing,
        private readonly ShippingConfigurationService $shipping,
        private readonly EasyParcelBookingGuard $easyParcelBookings,
        private readonly PaymentProofService $paymentProofs,
        private readonly OrderNotificationMilestoneService $notifications,
    ) {}

    public function availableTransitions(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

    /** @param Collection<int, array{variant: ProductVariant, quantity: int}> $items */
    /** @param array{code?: string, exchange_rate?: float}|null $currencySnapshot */
    public function checkout(string $email, Collection $items, ?Coupon $coupon = null, ?array $shippingAddress = null, string $gateway = 'simulated_card', string $delivery = 'standard', ?array $currencySnapshot = null, ?UploadedFile $paymentProof = null, ?string $bankAccountId = null): Order
    {
        $currencySnapshot ??= app(StorefrontCurrencyContext::class)->snapshot();

        return DB::transaction(function () use ($email, $items, $coupon, $shippingAddress, $gateway, $delivery, $currencySnapshot, $paymentProof, $bankAccountId): Order {
            app(\App\Services\Inventory\StoreManagement\RebaselineFreeze::class)->assertNewReservationAllowed();
            $normalizedEmail = strtolower(trim($email));
            $customer = Customer::query()
                ->whereRaw('lower(email) = ?', [$normalizedEmail])
                ->lockForUpdate()
                ->first()
                ?? Customer::query()->firstOrCreate(['email' => $normalizedEmail]);
            $lockedCoupon = null;
            if ($coupon) {
                $lockedCoupon = Coupon::query()->lockForUpdate()->find($coupon->id);
                $customerUses = $lockedCoupon?->usages()->where('customer_email', $email)->count() ?? 0;
                if (! $lockedCoupon || ! $lockedCoupon->isCurrentlyValid() || $customerUses >= $lockedCoupon->per_customer_limit) {
                    throw ValidationException::withMessages(['coupon' => 'This coupon is no longer available for this customer.']);
                }
            }
            $lines = $items->map(function (array $item): array {
                $variant = ProductVariant::query()->lockForUpdate()->findOrFail($item['variant']->id);
                $variant->setRelation('product', \App\Models\Product::query()->lockForUpdate()->findOrFail($variant->product_id));
                app(ProductPurchaseRules::class)->assertPurchasable($variant);
                $available = app(WebsiteInventoryAvailability::class)->quantity($variant);
                if ($item['quantity'] < 1 || $available === null || $available < $item['quantity']) {
                    throw new RuntimeException('Requested quantity is unavailable.');
                }

                return ['variant' => $variant, 'quantity' => $item['quantity'], 'line_total' => $variant->price * $item['quantity']];
            });
            $checkoutItems = $lines->map(fn (array $line) => ['variant' => $line['variant'], 'quantity' => $line['quantity']]);
            $shippingOption = $this->shipping->optionFor($checkoutItems, $lockedCoupon, $delivery, $shippingAddress);
            if (! $shippingOption) {
                throw ValidationException::withMessages(['delivery' => 'The selected delivery option is no longer available for this address.']);
            }
            $baseSummary = $this->pricing->summarize($checkoutItems, $lockedCoupon, $delivery, $shippingAddress);
            $availableMethods = $this->paymentMethods->checkoutMethods([
                'currency' => $currencySnapshot['code'] ?? 'MYR',
                'amount' => $baseSummary['total'],
                'address' => $shippingAddress ?? [],
            ]);
            $method = $availableMethods[$gateway] ?? null;
            if (! $method) {
                throw ValidationException::withMessages(['payment_method' => 'This payment method is not currently enabled.']);
            }
            $surcharge = (int) data_get($method, 'cod.surchargeCents', 0);
            $summary = $this->pricing->summarize($checkoutItems, $lockedCoupon, $delivery, $shippingAddress, $surcharge);
            $snapshot = $shippingOption['snapshot'];
            $snapshot['shipping_amount'] = $summary['shipping'];
            $order = Order::query()->create(['number' => 'JW-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'customer_id' => $customer->id, 'coupon_id' => $lockedCoupon?->id, 'coupon_code' => $lockedCoupon?->code, 'email' => $email, 'currency' => $currencySnapshot['code'] ?? 'USD', 'currency_exchange_rate' => $currencySnapshot['exchange_rate'] ?? 1, 'status' => 'pending_payment', 'shipping_method' => $shippingOption['id'], 'shipping_label' => $shippingOption['label'], 'shipping_snapshot' => $snapshot, 'subtotal' => $summary['subtotal'], 'discount_amount' => $summary['discount'], 'shipping_amount' => $summary['shipping'], 'payment_surcharge_amount' => $summary['payment_surcharge'], 'tax_amount' => $summary['tax'], 'total' => $summary['total']]);
            foreach ($lines as $line) {
                $variant = $line['variant'];
                $product = $variant->product()->with('images')->firstOrFail();
                OrderItem::query()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'sku' => $variant->sku, 'title' => $product->title, 'variant_title' => $variant->title, 'image_url' => $product->images->sortBy('position')->first()?->path, 'package_snapshot' => ['weight_grams' => $variant->weight_grams, 'length_cm' => $variant->package_length_cm, 'width_cm' => $variant->package_width_cm, 'height_cm' => $variant->package_height_cm], 'unit_price' => $variant->price, 'quantity' => $line['quantity'], 'line_total' => $line['line_total']]);
            }
            foreach ($order->items as $inventoryItem) {
                if (config('inventory.current_stock_only')) {
                    continue;
                }
                $activation = app(ManagedInventoryActivation::class)->eligible('website', (int) $inventoryItem->product_variant_id, $order->created_at);
                if ($activation) {
                    DB::table('order_items')->where('id', $inventoryItem->id)->update(['inventory_order_activation_id' => $activation['id']]);

                    continue;
                }
                if ($this->usesManagedStoreInventory()) {
                    $lineKey = 'order-item:'.$inventoryItem->id;
                    $router = app(OrderInventoryRouter::class);
                    $router->register('website', $lineKey, (int) $inventoryItem->product_variant_id, (int) $inventoryItem->quantity);
                    $router->apply('website', $lineKey, 'checkout:'.$lineKey.':reserved', 'reserved', (int) $inventoryItem->quantity);

                    continue;
                }
                app(StockAllocationService::class)->reserve('order', (int) $order->id, 'item:'.$inventoryItem->id, (int) $inventoryItem->product_variant_id, (int) $inventoryItem->quantity);
            }
            if (config('inventory.current_stock_only')) {
                app(WebsiteCurrentStockOrderEffects::class)->debit($order);
            }
            $this->persistTaxSnapshot($order, $summary['tax_quote']);
            $payment = $this->paymentAttempts->createPendingPayment($order, $method, $order->currency, $bankAccountId);
            if ($paymentProof) {
                if ($gateway !== 'bank_transfer' || ! data_get($method, 'bankTransfer.allowProofUpload', false)) {
                    throw ValidationException::withMessages(['payment_proof' => 'Receipt uploads are not enabled for this payment method.']);
                }

                $this->paymentProofs->storeCheckoutUpload($payment, $paymentProof);
            }
            if ($lockedCoupon) {
                $lockedCoupon->usages()->create(['order_id' => $order->id, 'customer_id' => $customer->id, 'customer_email' => $email, 'discount_amount' => $summary['discount']]);
                $lockedCoupon->increment('usage_count');
            }
            if ($shippingAddress) {
                $order->addresses()->create($shippingAddress + ['type' => 'shipping']);
            }
            OrderStatusHistory::query()->create(['order_id' => $order->id, 'to_status' => 'pending_payment']);
            $this->recordNotification($order, 'order_created', 'order', $order->id);

            return $order;
        });
    }

    /** @param array<string, mixed> $quote */
    private function persistTaxSnapshot(Order $order, array $quote): void
    {
        $line = collect($quote['lines'] ?? [])->first();
        if (! is_array($line) || ! filled($line['tax_class_id'] ?? null)) {
            return;
        }

        OrderTaxLine::query()->create([
            'order_id' => $order->id,
            'tax_rule_id' => null,
            'tax_class_id' => (int) $line['tax_class_id'],
            'label' => (string) $line['label'],
            'tax_type' => 'store_wide_percentage',
            'rate_percent' => (string) $line['rate_percent'],
            'taxable_amount' => (int) $line['taxable_amount'],
            'tax_amount' => (int) $line['tax_amount'],
            'configuration_snapshot' => [
                'quoted_at' => now()->toIso8601String(),
                'calculation_mode' => 'store_wide_percentage',
                'quote_line' => [
                    'kind' => 'merchandise',
                    'status' => $line['status'] ?? 'chargeable',
                ],
                'tax_class' => [
                    'id' => (int) $line['tax_class_id'],
                    'name' => (string) $line['label'],
                    'rate_percent' => (string) $line['rate_percent'],
                ],
            ],
        ]);
    }

    public function transition(
        Order $order,
        string $status,
        ?int $userId = null,
        ?string $note = null,
        bool $notifyCustomer = true,
    ): Order {
        return DB::transaction(function () use ($order, $status, $userId, $note, $notifyCustomer): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled' || $locked->status === $status) {
                return $locked;
            }
            if (! in_array($status, $this->availableTransitions($locked), true)) {
                throw new InvalidArgumentException('This order cannot move from '.$locked->status.' to '.$status.'.');
            }
            $this->easyParcelBookings->assertLegacyOrderMutationAllowed($locked);
            if ($status === 'cancelled') {
                if (config('inventory.current_stock_only')) {
                    app(WebsiteCurrentStockOrderEffects::class)->restore(
                        $locked,
                        app(WebsiteCurrentStockOrderEffects::class)->remaining($locked),
                        'cancelled'
                    );
                } else {
                foreach ($locked->items as $item) {
                    if ($item->product_variant_id) {
                        $storeLine = 'order-item:'.$item->id;
                        if (DB::table('inventory_store_order_lines')->where('line_key', $storeLine)->exists()) {
                            app(OrderInventoryRouter::class)
                                ->apply('website', $storeLine, 'checkout:'.$storeLine.':cancelled', 'cancelled', (int) $item->quantity);

                            continue;
                        }
                        $allocation = DB::table('stock_allocations')->where('owner_type', 'order')->where('owner_id', $locked->id)->where('line_key', 'item:'.$item->id)->first();
                        if ($allocation) {
                            app(StockAllocationService::class)->release((int) $allocation->id);
                        } elseif ($item->inventory_order_activation_id) {
                            continue;
                        } else {
                            if (DB::table('inventory_configuration')->where('id', 1)->value('accounting_enabled')) {
                                throw new InvalidArgumentException('Existing order requires warehouse reconciliation before cancellation.');
                            }
                            app(InventoryMutationService::class)->change((int) $item->product_variant_id, (int) $item->quantity, 'order-item:'.$item->id.':cancel', 'Order cancellation', $userId);
                        }
                    }
                }
                }
            }
            if ($status === 'paid') {
                $payment = $locked->payments()->latest('id')->first();
                $payment ? $payment->update(['status' => 'paid']) : OrderPayment::query()->create(['order_id' => $locked->id, 'gateway' => 'simulated', 'status' => 'paid']);
            }
            if ($status === 'cancelled') {
                $locked->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
            }
            $from = $locked->status;
            if ($status === 'shipped') {
                $this->consumeOrderAllocations($locked);
            }
            $locked->update(['status' => $status]);
            if ($status === 'paid') {
                app(OrderAllocationService::class)->reservePaid($locked);
            }
            $history = OrderStatusHistory::query()->create(['order_id' => $locked->id, 'from_status' => $from, 'to_status' => $status, 'user_id' => $userId, 'note' => $note]);
            if ($notifyCustomer && $status === 'cancelled') {
                $this->recordNotification(
                    $locked,
                    'cancelled',
                    'order_status_history',
                    $history->id,
                    extra: ['staff_note' => $note ?: 'No additional notes.'],
                );
            }

            return $locked;
        });
    }

    private function usesManagedStoreInventory(): bool
    {
        return in_array(DB::table('inventory_configuration')->where('id', 1)->value('operating_mode'), ['shared_pool', 'independent_stores'], true)
            && DB::table('inventory_store_accounts')->where('account_key', 'website')->where('management_status', 'managed')->exists();
    }

    public function dispatch(Order $order, string $carrier, string $trackingNumber, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $carrier, $trackingNumber, $userId): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'processing') {
                throw new InvalidArgumentException('Only processing orders can be dispatched.');
            }
            $this->easyParcelBookings->assertManualDispatchAllowed($locked);

            $from = $locked->status;
            $this->consumeOrderAllocations($locked);
            $locked->update([
                'status' => 'shipped',
                'tracking_carrier' => $carrier,
                'tracking_number' => $trackingNumber,
                'shipped_at' => now(),
            ]);
            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => 'shipped',
                'user_id' => $userId,
                'note' => 'Dispatched via '.$carrier.' · '.$trackingNumber,
            ]);

            return $locked;
        });
    }

    private function consumeOrderAllocations(Order $order): void
    {
        if (config('inventory.current_stock_only')) {
            return;
        }
        $managed = false;
        foreach ($order->items()->orderBy('id')->get() as $item) {
            $lineKey = 'order-item:'.$item->id;
            if (! DB::table('inventory_store_order_lines')->where('line_key', $lineKey)->exists()) {
                continue;
            }
            $managed = true;
            app(OrderInventoryRouter::class)
                ->apply('website', $lineKey, 'checkout:'.$lineKey.':shipped', 'shipped', (int) $item->quantity);
        }
        if ($managed) {
            return;
        }
        app(OrderAllocationService::class)->consume($order);
    }

    /** @param array<string, mixed> $extra */
    private function recordNotification(
        Order $order,
        string $eventKey,
        string $sourceType,
        string|int $sourceId,
        array $extra = [],
    ): void {
        $this->notifications->record($order, $eventKey, $sourceType, $sourceId, extra: $extra);
    }
}
