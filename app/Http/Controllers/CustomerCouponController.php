<?php

namespace App\Http\Controllers;

use App\Services\CustomerCouponEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerCouponController extends Controller
{
    public function apply(Request $request, CustomerCouponEligibilityService $coupons): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:255']]);
        $customer = $request->user('customer')->customer;
        abort_unless($customer !== null, 403);

        $result = $coupons->apply($customer, $data['code'], $request);

        return redirect()->to(route('customer.account').'#vouchers')->with('coupon_status', [
            'type' => $result->applied ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }
}
