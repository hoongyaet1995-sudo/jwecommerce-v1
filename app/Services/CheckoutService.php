<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderStatusHistory;
use App\Models\OrderTaxLine;
use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Models\TaxConfiguration;
use App\Models\TaxRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Validation\ValidationException;

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
    )
    {
    }

    public function availableTransitions(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

    /** @param Collection<int, array{variant: ProductVariant, quantity: int}> $items */
    /** @param array{code?: string, exchange_rate?: float}|null $currencySnapshot */
    public function checkout(string $email, Collection $items, ?Coupon $coupon = null, ?array $shippingAddress = null, string $gateway = 'simulated_card', string $delivery = 'standard', ?array $currencySnapshot = null, ?UploadedFile $paymentProof = null, ?string $bankAccountId = null): Order
    {
        $currencySnapshot ??= app(\App\Support\StorefrontCurrencyContext::class)->snapshot();

        return DB::transaction(function () use ($email, $items, $coupon, $shippingAddress, $gateway, $delivery, $currencySnapshot, $paymentProof, $bankAccountId): Order {
            $customer = Customer::query()->firstOrCreate(['email' => $email]);
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
                if ($item['quantity'] < 1 || $variant->stock < $item['quantity']) throw new RuntimeException('Requested quantity is unavailable.');
                $variant->decrement('stock', $item['quantity']);
                return ['variant' => $variant, 'quantity' => $item['quantity'], 'line_total' => $variant->price * $item['quantity']];
            });
            $checkoutItems = $lines->map(fn (array $line) => ['variant' => $line['variant'], 'quantity' => $line['quantity']]);
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
            $order = Order::query()->create(['number' => 'JW-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT), 'customer_id' => $customer->id, 'coupon_id' => $lockedCoupon?->id, 'coupon_code' => $lockedCoupon?->code, 'email' => $email, 'currency' => $currencySnapshot['code'] ?? 'USD', 'currency_exchange_rate' => $currencySnapshot['exchange_rate'] ?? 1, 'status' => 'pending_payment', 'shipping_method' => $delivery, 'shipping_label' => $this->pricing->deliveryLabel($delivery), 'subtotal' => $summary['subtotal'], 'discount_amount' => $summary['discount'], 'shipping_amount' => $summary['shipping'], 'payment_surcharge_amount' => $summary['payment_surcharge'], 'tax_amount' => $summary['tax'], 'total' => $summary['total']]);
            foreach ($lines as $line) {
                $variant = $line['variant'];
                $product = $variant->product()->with('images')->firstOrFail();
                OrderItem::query()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id, 'sku' => $variant->sku, 'title' => $product->title, 'variant_title' => $variant->title, 'image_url' => $product->images->sortBy('position')->first()?->path, 'unit_price' => $variant->price, 'quantity' => $line['quantity'], 'line_total' => $line['line_total']]);
            }
            $payment = $this->paymentAttempts->createPendingPayment($order, $method, $order->currency, $bankAccountId);
            $this->persistTaxSnapshot($order, $summary['tax_quote']);
            if ($paymentProof) {
                if ($gateway !== 'bank_transfer' || ! data_get($method, 'bankTransfer.allowProofUpload', false)) {
                    throw ValidationException::withMessages(['payment_proof' => 'Receipt uploads are not enabled for this payment method.']);
                }

                $payment->update([
                    'proof_path' => $paymentProof->store('payment-proofs/'.$order->id, 'public'),
                    'proof_uploaded_at' => now(),
                ]);
            }
            if ($lockedCoupon) {
                $lockedCoupon->usages()->create(['order_id' => $order->id, 'customer_id' => $customer->id, 'customer_email' => $email, 'discount_amount' => $summary['discount']]);
                $lockedCoupon->increment('usage_count');
            }
            if ($shippingAddress) $order->addresses()->create($shippingAddress + ['type' => 'shipping']);
            OrderStatusHistory::query()->create(['order_id' => $order->id, 'to_status' => 'pending_payment']);
            return $order;
        });
    }

    /** @param array{tax: int, status: string, reason: string, lines: list<array<string, mixed>>} $quote */
    private function persistTaxSnapshot(Order $order, array $quote): void
    {
        $matchedLines = collect($quote['lines'] ?? [])
            ->filter(fn (array $line): bool => filled($line['rule_id'] ?? null))
            ->values();

        if ($matchedLines->isEmpty()) {
            return;
        }

        $rules = TaxRule::query()
            ->with(['taxClass', 'taxZone'])
            ->whereIn('id', $matchedLines->pluck('rule_id')->unique())
            ->get()
            ->keyBy('id');
        $configuration = TaxConfiguration::query()->first();

        foreach ($matchedLines as $line) {
            $rule = $rules->get($line['rule_id']);
            if (! $rule) {
                throw new RuntimeException('A matched tax rule is no longer available for checkout.');
            }

            OrderTaxLine::query()->create([
                'order_id' => $order->id,
                'tax_rule_id' => $rule->id,
                'tax_class_id' => $rule->tax_class_id,
                'label' => (string) $line['label'],
                'tax_type' => (string) $rule->tax_type,
                'rate_percent' => (string) $line['rate_percent'],
                'taxable_amount' => (int) $line['taxable_amount'],
                'tax_amount' => (int) $line['tax_amount'],
                'configuration_snapshot' => [
                    'quoted_at' => now()->toIso8601String(),
                    'quote_line' => [
                        'kind' => $line['kind'] ?? 'merchandise',
                        'status' => $line['status'] ?? 'chargeable',
                        'variant_id' => $line['variant_id'] ?? null,
                    ],
                    'configuration' => $configuration ? [
                        'id' => $configuration->id,
                        'is_enabled' => $configuration->is_enabled,
                        'is_published' => $configuration->is_published,
                        'prices_include_tax' => $configuration->prices_include_tax,
                        'address_basis' => $configuration->address_basis,
                        'merchant_compliance_metadata' => $configuration->merchant_compliance_metadata,
                    ] : null,
                    'tax_rule' => [
                        'id' => $rule->id,
                        'label' => $rule->label,
                        'tax_type' => $rule->tax_type,
                        'rate_percent' => (string) $rule->rate_percent,
                        'applies_to_shipping' => $rule->applies_to_shipping,
                        'priority' => $rule->priority,
                        'effective_from' => $rule->effective_from?->toIso8601String(),
                        'effective_until' => $rule->effective_until?->toIso8601String(),
                    ],
                    'tax_class' => $rule->taxClass ? [
                        'id' => $rule->taxClass->id,
                        'code' => $rule->taxClass->code,
                        'name' => $rule->taxClass->name,
                        'description' => $rule->taxClass->description,
                    ] : null,
                    'tax_zone' => $rule->taxZone ? [
                        'id' => $rule->taxZone->id,
                        'code' => $rule->taxZone->code,
                        'name' => $rule->taxZone->name,
                        'country' => $rule->taxZone->country,
                        'state' => $rule->taxZone->state,
                        'postcode' => $rule->taxZone->postcode,
                    ] : null,
                ],
            ]);
        }
    }

    public function transition(Order $order, string $status, ?int $userId = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $userId, $note): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled' || $locked->status === $status) return $locked;
            if (! in_array($status, $this->availableTransitions($locked), true)) throw new InvalidArgumentException('This order cannot move from '.$locked->status.' to '.$status.'.');
            if ($status === 'cancelled') foreach ($locked->items as $item) { if ($item->product_variant_id) ProductVariant::query()->whereKey($item->product_variant_id)->increment('stock', $item->quantity); }
            if ($status === 'paid') {
                $payment = $locked->payments()->latest('id')->first();
                $payment ? $payment->update(['status' => 'paid']) : OrderPayment::query()->create(['order_id' => $locked->id, 'gateway' => 'simulated', 'status' => 'paid']);
            }
            if ($status === 'cancelled') $locked->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
            $from = $locked->status; $locked->update(['status' => $status]);
            OrderStatusHistory::query()->create(['order_id' => $locked->id, 'from_status' => $from, 'to_status' => $status, 'user_id' => $userId, 'note' => $note]);
            return $locked;
        });
    }

    public function dispatch(Order $order, string $carrier, string $trackingNumber, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $carrier, $trackingNumber, $userId): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'processing') {
                throw new InvalidArgumentException('Only processing orders can be dispatched.');
            }

            $from = $locked->status;
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
}
