<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View { return view('admin.coupons', ['coupons' => Coupon::query()->latest()->get()]); }
    public function store(Request $request): RedirectResponse { Coupon::query()->create($this->validated($request)); return redirect()->route('admin.coupons.index'); }
    public function update(Request $request, Coupon $coupon): RedirectResponse { $coupon->update($this->validated($request, $coupon)); return redirect()->route('admin.coupons.index'); }
    public function destroy(Coupon $coupon): RedirectResponse { $coupon->delete(); return redirect()->route('admin.coupons.index'); }
    private function validated(Request $request, ?Coupon $coupon = null): array { $data = $request->validate(['code' => ['required','string','max:255','alpha_dash','unique:coupons,code'.($coupon ? ','.$coupon->id : '')], 'discount_type' => ['required','in:percentage,fixed_amount,free_shipping'], 'value' => ['required','integer','min:0'], 'min_spend' => ['nullable','integer','min:0'], 'is_active' => ['required','boolean'], 'starts_at' => ['nullable','date'], 'ends_at' => ['nullable','date','after_or_equal:starts_at']]); $data['code'] = strtoupper($data['code']); return $data; }
}
