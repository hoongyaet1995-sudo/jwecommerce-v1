<?php

namespace App\Domain\Accounts;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Services\CustomerCouponAvailability;
use App\Services\CustomerCouponEligibilityService;
use App\Support\StorefrontCurrencyContext;

final class CustomerCouponViewModelFactory
{
    public function __construct(
        private readonly CustomerCouponEligibilityService $eligibility,
        private readonly StorefrontCurrencyContext $currency,
    )
    {
    }

    public function for(Customer $customer): array
    {
        $eligible = collect();
        $expired = collect();

        Coupon::query()->orderByDesc('created_at')->get()->each(function (Coupon $coupon) use ($customer, $eligible, $expired): void {
            $card = $this->couponCard($coupon, $this->eligibility->availabilityFor($customer, $coupon));
            ($card['status'] === 'eligible' ? $eligible : $expired)->push($card);
        });

        $used = CouponUsage::query()->with(['coupon', 'order'])
            ->where('customer_id', $customer->id)
            ->orWhere(function ($query) use ($customer): void {
                $query->whereNull('customer_id')->whereRaw('lower(customer_email) = ?', [strtolower($customer->email)]);
            })->latest()->get()->map(fn (CouponUsage $usage) => [
                'id' => 'usage-'.$usage->id, 'code' => $usage->coupon?->code ?? 'Deleted coupon',
                'title' => $usage->coupon?->name ?? 'Coupon', 'description' => $usage->order?->number ?? 'Order usage',
                'discountType' => $usage->coupon?->discount_type ?? 'fixed_amount',
                'value' => $usage->discount_amount, 'discountDisplay' => $this->currency->format($usage->discount_amount),
                'minSpend' => 0, 'minSpendDisplay' => $usage->order?->number ? 'Redeemed on '.$usage->order->number : 'Redeemed',
                'status' => 'used', 'statusLabel' => 'REDEEMED', 'orderNumber' => $usage->order?->number,
            ]);

        return ['eligible' => $eligible->values()->all(), 'used' => $used->values()->all(), 'expired' => $expired->values()->all(), 'counts' => ['eligible' => $eligible->count(), 'used' => $used->count(), 'expired' => $expired->count()]];
    }

    private function couponCard(Coupon $coupon, CustomerCouponAvailability $availability): array
    {
        $discountDisplay = match ($coupon->discount_type) {
            'percentage' => $coupon->value.'%',
            'free_shipping' => 'Free shipping',
            default => $this->currency->format($coupon->value),
        };

        return ['id' => 'coupon-'.$coupon->id, 'code' => $coupon->code, 'title' => $coupon->name ?: $coupon->code,
            'description' => $coupon->description, 'discountType' => $coupon->discount_type, 'value' => $coupon->value, 'discountDisplay' => $discountDisplay,
            'minSpend' => $coupon->min_spend ?? 0, 'minSpendDisplay' => $coupon->min_spend ? 'Min. spend '.$this->currency->format($coupon->min_spend) : 'No minimum spend',
            'status' => $availability->usable ? 'eligible' : 'expired', 'statusLabel' => $availability->usable ? 'ACTIVE' : 'UNAVAILABLE'];
    }
}
