<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Support\StorefrontCurrencyContext;
use Illuminate\Http\Request;

final class CustomerCouponEligibilityService
{
    public function __construct(
        private readonly CartViewData $cartViewData,
        private readonly StorefrontCurrencyContext $currency,
    ) {
    }

    public function apply(?Customer $customer, string $code, Request $request): CustomerCouponApplicationResult
    {
        $coupon = Coupon::query()
            ->whereRaw('lower(code) = ?', [strtolower(trim($code))])
            ->first();

        $availability = $coupon === null
            ? CustomerCouponAvailability::rejected('Coupon code is not valid.')
            : $this->availabilityFor($customer, $coupon);

        if (! $availability->usable) {
            return CustomerCouponApplicationResult::rejected($availability->message);
        }

        $subtotal = (int) data_get($this->cartViewData->for($request), 'summary.subtotal', 0);
        if ($coupon->min_spend !== null && $subtotal < $coupon->min_spend) {
            return CustomerCouponApplicationResult::rejected(
                'Coupon requires a minimum spend of '.$this->currency->format($coupon->min_spend).'.'
            );
        }

        $request->session()->put('coupon_code', $coupon->code);

        return CustomerCouponApplicationResult::applied($coupon->code);
    }

    public function availabilityFor(?Customer $customer, Coupon $coupon): CustomerCouponAvailability
    {
        if (! $coupon->isCurrentlyValid()) {
            return CustomerCouponAvailability::rejected('Coupon code is not valid.');
        }

        if ($customer === null) {
            return CustomerCouponAvailability::usable();
        }

        $usageCount = $coupon->usages()
            ->where(function ($query) use ($customer): void {
                $query->where('customer_id', $customer->id)
                    ->orWhere(function ($legacy) use ($customer): void {
                        $legacy->whereNull('customer_id')
                            ->whereRaw('lower(customer_email) = ?', [strtolower($customer->email)]);
                    });
            })
            ->count();

        if ($usageCount >= $coupon->per_customer_limit) {
            return CustomerCouponAvailability::rejected('This coupon is no longer available for this customer.');
        }

        return CustomerCouponAvailability::usable();
    }
}
