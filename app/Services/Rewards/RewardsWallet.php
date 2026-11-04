<?php

namespace App\Services\Rewards;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One shared, transactional ledger for every storefront theme. Amounts are minor units. */
class RewardsWallet
{
    public function rules(): array
    {
        return StoreSetting::where('key', 'rewards_rules')->value('value') ?? [];
    }

    public function customerId(): ?int
    {
        return auth('customer')->user()?->customer?->id;
    }

    public function balance(int $customerId): int
    {
        $wallet = DB::table('reward_wallets')->where('customer_id', $customerId)->value('id');
        if (!$wallet) return 0;
        // Negative lots represent reward clawbacks already spent; future credits repay them.
        return max(0, (int) DB::table('reward_lots')->where('wallet_id', $wallet)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())->orWhere('remaining', '<', 0))
            ->sum('remaining'));
    }

    public function quote(int $eligible, ?int $customerId, bool $use): array
    {
        $rules = $this->rules();
        $balance = ($customerId && ($rules['enabled'] ?? false)) ? $this->balance($customerId) : 0;
        $conversion = max(1, (int) ($rules['coins_per_unit'] ?? 100));
        $cap = intdiv(max(0, $eligible) * min(100, max(0, (int) ($rules['max_discount_percent'] ?? 20))), 100);
        $coins = min($balance, intdiv($cap * $conversion, 100));
        // Only reserve whole redeemable minor units, avoiding lost fractional coins.
        $amount = intdiv($coins * 100, $conversion);
        $coins = (int) ceil($amount * $conversion / 100);
        if (!($rules['enabled'] ?? false) || $coins < (int) ($rules['minimum_coins'] ?? 100)) $coins = $amount = 0;
        return ['balance' => $balance, 'available_coins' => $coins, 'available_amount' => $amount,
            'coins' => $use ? $coins : 0, 'amount' => $use ? $amount : 0, 'conversion' => $conversion];
    }

    public function attach(Order $order, array $quote, ?array $estimate): void
    {
        if (!$order->customer_id || $order->shipping_method === 'quoted_manual' || !($this->rules()['enabled'] ?? false)) return;
        $wallet = $this->lockWallet($order->customer_id);
        $coins = (int) ($quote['coins'] ?? 0);
        if ($coins && $this->customerId() !== (int) $order->customer_id) {
            throw ValidationException::withMessages(['rewards' => 'Sign in to use your rewards.']);
        }
        if ($coins > $this->balance($order->customer_id)) {
            throw ValidationException::withMessages(['rewards' => 'Your rewards balance changed. Review checkout and try again.']);
        }
        $allocations = [];
        $left = $coins;
        $lots = DB::table('reward_lots')->where('wallet_id', $wallet)->where('remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            $take = min($left, $lot->remaining);
            if ($take > 0) {
                DB::table('reward_lots')->where('id', $lot->id)->decrement('remaining', $take);
                $allocations[] = ['id' => $lot->id, 'coins' => $take];
                $left -= $take;
            }
        }
        if ($left) throw new \LogicException('Insufficient rewards allocation.');
        $snapshot = ['version' => 1, 'earned' => (int) ($estimate['coins'] ?? 0), 'offer_id' => $estimate['offer_id'] ?? null,
            'redeemed' => $coins, 'amount' => (int) ($quote['amount'] ?? 0),
            'expiry_months' => $this->rules()['expiry_months'] ?? null, 'order_total' => (int) $order->total];
        DB::table('order_rewards')->insert(['order_id' => $order->id, 'wallet_id' => $wallet,
            'snapshot' => json_encode($snapshot), 'redemption_lots' => json_encode($allocations), 'created_at' => now(), 'updated_at' => now()]);
        if ($coins) $this->entry($wallet, $order->id, 'reserve:'.$order->id, 'used', -$coins);
    }

    public function synchronize(Order $order): void
    {
        if (!DB::table('order_rewards')->where('order_id', $order->id)->exists()) return;
        DB::transaction(function () use ($order) {
            // Lock order first everywhere, then wallet, then snapshot to serialize callbacks.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $row = DB::table('order_rewards')->where('order_id', $order->id)->first();
            DB::table('reward_wallets')->where('id', $row->wallet_id)->lockForUpdate()->first();
            $row = DB::table('order_rewards')->where('id', $row->id)->lockForUpdate()->first();
            $snapshot = json_decode($row->snapshot, true);
            $cancelled = $order->status === 'cancelled';
            $paid = (int) $order->payments()->whereIn('status', ['paid', 'captured', 'succeeded'])->sum('amount') >= (int) $order->total;
            if ($paid && !$cancelled && !$row->credited && $snapshot['earned'] > 0) {
                $months = $snapshot['expiry_months'];
                DB::table('reward_lots')->insert(['wallet_id' => $row->wallet_id, 'source' => 'order:'.$order->id,
                    'coins' => $snapshot['earned'], 'remaining' => $snapshot['earned'],
                    'expires_at' => $months ? now()->addMonthsNoOverflow((int) $months) : null, 'created_at' => now(), 'updated_at' => now()]);
                $this->entry($row->wallet_id, $order->id, 'earn:'.$order->id, 'earned', $snapshot['earned']);
                DB::table('order_rewards')->where('id', $row->id)->update(['credited' => $snapshot['earned']]);
                $row->credited = $snapshot['earned'];
            }
            $refund = (int) $order->refunds()->where('status', 'completed')->sum('amount');
            $denominator = max(1, (int) $snapshot['order_total']);
            $numerator = $cancelled ? $denominator : min($refund, $denominator);
            $reverse = intdiv($row->credited * $numerator, $denominator);
            $restore = intdiv($snapshot['redeemed'] * $numerator, $denominator);
            if ($reverse > $row->reversed) {
                $delta = $reverse - $row->reversed;
                DB::table('reward_lots')->where('source', 'order:'.$order->id)->decrement('remaining', $delta);
                $this->entry($row->wallet_id, $order->id, 'reverse:'.$order->id.':'.$reverse, 'refund_adjustment', -$delta);
            }
            if ($restore > $row->restored) {
                $remaining = $restore - $row->restored;
                $skip = $row->restored;
                foreach (json_decode($row->redemption_lots, true) as $allocation) {
                    $skipped = min($skip, $allocation['coins']);
                    $skip -= $skipped;
                    $add = min($remaining, $allocation['coins'] - $skipped);
                    if ($add) DB::table('reward_lots')->where('id', $allocation['id'])->increment('remaining', $add);
                    $remaining -= $add;
                }
                // Original expiry remains intact, including on returned coins.
                $this->entry($row->wallet_id, $order->id, 'restore:'.$order->id.':'.$restore, 'returned', $restore - $row->restored);
            }
            DB::table('order_rewards')->where('id', $row->id)->update(['reversed' => max($reverse, $row->reversed),
                'restored' => max($restore, $row->restored), 'updated_at' => now()]);
        });
    }

    private function lockWallet(int $customerId): int
    {
        DB::table('reward_wallets')->insertOrIgnore(['customer_id' => $customerId, 'created_at' => now(), 'updated_at' => now()]);
        return (int) DB::table('reward_wallets')->where('customer_id', $customerId)->lockForUpdate()->value('id');
    }

    private function entry(int $wallet, int $order, string $key, string $kind, int $coins): void
    {
        DB::table('reward_entries')->insert(['wallet_id' => $wallet, 'order_id' => $order, 'event_key' => $key,
            'kind' => $kind, 'coins' => $coins, 'created_at' => now(), 'updated_at' => now()]);
    }
}
