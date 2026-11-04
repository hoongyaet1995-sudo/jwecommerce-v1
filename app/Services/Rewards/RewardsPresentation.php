<?php

namespace App\Services\Rewards;

use App\Models\StoreSetting;
use Carbon\CarbonImmutable;

/** Shared read-only estimate. This class never credits or spends a balance. */
class RewardsPresentation
{
    public function estimate(int $subtotal, int $discount = 0): ?array
    {
        $rules=StoreSetting::where('key','rewards_rules')->value('value') ?? [];
        if (!($rules['enabled'] ?? false)) return null;
        $eligible=max(0,$subtotal-max(0,$discount));
        if (!$eligible) return null;
        $conversion=max(1,(int)($rules['coins_per_unit'] ?? 100));
        $best=null;
        foreach (StoreSetting::where('key','like','rewards_offer:%')->get() as $setting) {
            $offer=$setting->value;
            if (!is_array($offer) || !($offer['enabled'] ?? false) || $eligible<(int)($offer['minimum_spend']??0)) continue;
            try {
                if (!empty($offer['starts_at']) && CarbonImmutable::parse($offer['starts_at'])->isFuture()) continue;
                if (!empty($offer['ends_at']) && CarbonImmutable::parse($offer['ends_at'])->isPast()) continue;
            } catch (\Throwable) { continue; }
            $basisPoints=(int)round(min(100,max(0,(float)($offer['rate']??0)))*100);
            $rewardMinor=min((int)($offer['maximum_reward']??0),intdiv($eligible*$basisPoints,10000));
            $coins=intdiv(max(0,$rewardMinor)*$conversion,100);
            if ($coins>($best['coins']??0)) $best=['coins'=>$coins,'offer_id'=>$offer['id']??substr($setting->key,14),'rate'=>$offer['rate'],'eligible_amount'=>$eligible];
        }
        return $best;
    }
}
