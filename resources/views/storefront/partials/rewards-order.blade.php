@php
    $orderReward = isset($order) && $order->exists ? \Illuminate\Support\Facades\DB::table('order_rewards')->where('order_id', $order->id)->first() : null;
    $rewardSnapshot = $orderReward ? json_decode($orderReward->snapshot, true) : [];
@endphp
@if($orderReward && (($rewardSnapshot['earned'] ?? 0) || ($rewardSnapshot['redeemed'] ?? 0)))
<section class="store-rewards" data-rewards-order aria-label="Order rewards">
    <div class="store-rewards-title">@include('storefront.partials.promotion-icon', ['promotionIcon'=>'coins']) <strong>Your rewards</strong></div>
    @if($order->status === 'cancelled')
        <p>Rewards adjusted for this cancelled order.</p>
    @elseif($orderReward->credited)
        <p>{{ number_format(max(0, $orderReward->credited - $orderReward->reversed)) }} coins earned</p>
    @elseif($rewardSnapshot['earned'])
        <p>{{ number_format($rewardSnapshot['earned']) }} coins pending</p><small>Available after payment is verified.</small>
    @endif
    @if($rewardSnapshot['redeemed'])<p>{{ number_format($rewardSnapshot['redeemed']) }} coins used · @storefrontMoney($rewardSnapshot['amount'])</p>@endif
    @if($orderReward->restored)<small>{{ number_format($orderReward->restored) }} coins returned. Original expiry applies.</small>@endif
</section>
@endif
