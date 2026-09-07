<?php

namespace App\Services\Returns;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Support\ReturnPolicySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRequestService
{
    public function __construct(private readonly ReturnPolicySettings $policySettings) {}

    public function create(int $customerId, OrderItem $item, array $data, array $files): ReturnRequest
    {
        $policy = $this->policySettings->get();
        if (! $policy['enabled']) {
            throw ValidationException::withMessages(['returns' => 'Returns and exchanges are currently unavailable.']);
        }
        $item->loadMissing('order.statusHistory');
        abort_unless((int) $item->order->customer_id === $customerId, 404);
        $deliveredAt = $item->order->statusHistory->where('to_status', 'delivered')->sortByDesc('created_at')->first()?->created_at;
        if ($item->order->status !== 'delivered' || ! $deliveredAt || $deliveredAt->copy()->addDays($policy['returnWindowDays'])->isPast()) {
            throw ValidationException::withMessages(['order_item_id' => "This item is outside the {$policy['returnWindowDays']}-day return window."]);
        }
        $already = (int) ReturnRequest::query()->where('order_item_id', $item->id)->whereNotIn('status', ['rejected'])->sum('quantity');
        if ($data['quantity'] > $item->quantity - $already) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds the remaining eligible quantity.']);
        }

        return DB::transaction(function () use ($customerId, $item, $data, $files, $deliveredAt, $policy): ReturnRequest {
            $request = ReturnRequest::query()->create([
                'order_id' => $item->order_id, 'order_item_id' => $item->id, 'customer_id' => $customerId, 'status' => 'requested',
                'resolution' => $data['resolution'], 'quantity' => $data['quantity'], 'reason' => $data['reason'], 'details' => $data['details'] ?? null,
                'eligible_until' => $deliveredAt->copy()->addDays($policy['returnWindowDays']),
                'policy_window_days' => $policy['returnWindowDays'],
                'policy_summary_snapshot' => $policy['policySummary'],
            ]);
            $request->update(['number' => 'RMA-'.now()->format('Y').'-'.str_pad((string) $request->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($files as $file) {
                /** @var UploadedFile $file */
                $path = $file->store("returns/{$request->id}", 'local');
                $request->evidence()->create(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(), 'size' => $file->getSize()]);
            }
            $request->events()->create(['event_type' => 'requested', 'actor_type' => 'customer', 'actor_id' => $customerId, 'note' => 'Customer submitted a return request.']);

            return $request->fresh(['item', 'evidence', 'events']);
        });
    }

    public function transition(ReturnRequest $request, string $action, User $actor, array $data = []): ReturnRequest
    {
        return DB::transaction(function () use ($request, $action, $actor, $data): ReturnRequest {
            $r = ReturnRequest::query()->lockForUpdate()->findOrFail($request->id);
            $allowed = ['approve' => ['requested', 'approved'], 'reject' => ['requested', 'rejected'], 'receive' => ['approved', 'received'], 'resolve_refund' => ['received', 'resolved_refund'], 'resolve_replacement' => ['received', 'resolved_replacement']];
            [$from,$to] = $allowed[$action] ?? [null, null];
            if ($r->status !== $from) {
                throw ValidationException::withMessages(['status' => 'This return request is no longer in the required state.']);
            }
            $updates = ['status' => $to];
            if ($action === 'approve') {
                $updates['approved_at'] = now();
            }
            if ($action === 'reject') {
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'A customer-visible rejection reason is required.']);
                } $updates += ['rejection_reason' => $data['reason'], 'resolved_at' => now()];
            }
            if ($action === 'receive') {
                if ($data['condition'] === 'restockable' && $r->item->product_variant_id) {
                    app(\App\Services\Inventory\InventoryMutationService::class)->change((int)$r->item->product_variant_id, (int)$data['received_quantity'], 'return:'.$r->id.':receive', 'Restockable return received', $actor->id);
                }
                $updates += ['received_quantity' => $data['received_quantity'], 'condition' => $data['condition'], 'warehouse_note' => $data['warehouse_note'] ?? null, 'received_at' => now()];
            }
            if ($action === 'resolve_refund') {
                $r->loadMissing('order.payments', 'item');
                $payment = $r->order->payments()->latest('id')->lockForUpdate()->firstOrFail();
                $rate = (float) ($r->order->currency_exchange_rate ?? 1);
                if ($rate <= 0) {
                    throw ValidationException::withMessages(['refund_amount' => 'The historical currency rate is invalid.']);
                }
                $amount = (int) round(((float) ($data['refund_amount'] ?? 0)) * 100 / $rate);
                $refunded = (int) $r->order->refunds()->where('status', 'completed')->sum('amount');
                if ($amount <= 0 || ! in_array($payment->status, ['paid', 'partially_refunded'], true) || ((float) $data['refund_amount']) * 100 > round(($r->order->total - $refunded) * $rate) || $amount > $r->order->total - $refunded) {
                    throw ValidationException::withMessages(['status' => 'The order does not have enough refundable paid balance.']);
                }
                $refund = OrderRefund::query()->create(['order_id' => $r->order_id, 'order_payment_id' => $payment->id, 'user_id' => $actor->id, 'amount' => $amount, 'reason' => "Externally completed refund for return {$r->number}; reference {$data['external_refund_reference']}", 'status' => 'completed']);
                $payment->update(['status' => $amount === $r->order->total - $refunded ? 'refunded' : 'partially_refunded']);
                $updates += ['resolved_refund_id' => $refund->id, 'external_refund_reference' => $data['external_refund_reference'], 'manual_refund_note' => $data['refund_note'], 'resolved_at' => now()];
            }
            if ($action === 'resolve_replacement') {
                $replacement = $this->replacementOrder($r);
                $updates += ['replacement_order_id' => $replacement->id, 'resolved_at' => now()];
            }
            $r->update($updates);
            $r->events()->create(['event_type' => $to, 'actor_type' => 'staff', 'actor_id' => $actor->id, 'note' => $data['note'] ?? $data['warehouse_note'] ?? $data['reason'] ?? null, 'metadata' => $data ?: null]);

            return $r->fresh(['item', 'evidence', 'events', 'replacementOrder']);
        });
    }

    private function replacementOrder(ReturnRequest $r): Order
    {
        $r->loadMissing('order.addresses', 'item');
        $source = $r->order;
        $order = Order::query()->create(['number' => $source->number.'-REP-'.$r->id, 'customer_id' => $source->customer_id, 'email' => $source->email, 'currency' => $source->currency, 'status' => 'processing', 'subtotal' => 0, 'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 0, 'internal_note' => "Same-SKU replacement for {$r->number}"]);
        $order->items()->create(['product_variant_id' => $r->item->product_variant_id, 'sku' => $r->item->sku, 'title' => $r->item->title, 'variant_title' => $r->item->variant_title, 'image_url' => $r->item->image_url, 'package_snapshot' => $r->item->package_snapshot, 'unit_price' => 0, 'quantity' => $r->quantity, 'line_total' => 0]);
        foreach ($source->addresses as $a) {
            $order->addresses()->create($a->only(['type', 'first_name', 'last_name', 'phone', 'line_1', 'line_2', 'city', 'state', 'postal_code', 'country']));
        }
        $order->statusHistory()->create(['to_status' => 'processing', 'note' => "Replacement order created for {$r->number}"]);

        return $order;
    }
}
