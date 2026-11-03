<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\CouponUsage;

class CouponApiController extends Controller
{
    public function index(): JsonResponse
    {
        $usages = CouponUsage::query()->with(['coupon', 'order', 'customer'])->latest()->get()->map(fn (CouponUsage $usage): array => [
            'id'=>(string) $usage->id,'promotionId'=>(string) $usage->coupon_id,'promotionName'=>$usage->coupon?->name ?? $usage->coupon?->code ?? 'Coupon','couponCode'=>$usage->coupon?->code,
            'orderNumber'=>$usage->order?->number ?? 'Deleted order','customerName'=>trim(($usage->customer?->first_name ?? '').' '.($usage->customer?->last_name ?? '')) ?: $usage->customer_email,
            'customerEmail'=>$usage->customer_email,'discountAmount'=>$usage->discount_amount / 100,'cashbackCoinsIssued'=>0,'status'=>'settled','createdAt'=>$usage->created_at?->toIso8601String(),
        ]);
        return response()->json(['coupons' => Coupon::query()->latest()->get()->map(fn (Coupon $coupon): array => $this->data($coupon)), 'usages' => $usages]);
    }

    public function store(Request $request): JsonResponse
    {
        $coupon = Coupon::query()->create($this->validated($request));
        return response()->json(['data' => $this->data($coupon)], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($this->validated($request, $coupon));
        return response()->json(['data' => $this->data($coupon->fresh())]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();
        return response()->json([], 204);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        if ($request->filled('code')) $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon)],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'discountType' => ['required', Rule::in(['percentage', 'fixed_amount', 'free_shipping'])],
            'value' => ['required', 'numeric', 'min:0'], 'minimumSpend' => ['nullable', 'numeric', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'], 'perCustomerLimit' => ['required', 'integer', 'min:1'],
            'isActive' => ['required', 'boolean'], 'startsAt' => ['nullable', 'date'], 'endsAt' => ['nullable', 'date', ...($request->filled('startsAt') ? ['after_or_equal:startsAt'] : [])],
        ]);
        $fixed = $data['discountType'] === 'fixed_amount';
        return ['code' => strtoupper($data['code']), 'name' => $data['name'], 'description' => $data['description'] ?? null, 'discount_type' => $data['discountType'],
            'value' => $fixed ? (int) round($data['value'] * 100) : (int) round($data['value']), 'min_spend' => isset($data['minimumSpend']) ? (int) round($data['minimumSpend'] * 100) : null,
            'usage_limit' => $data['usageLimit'] ?? null, 'per_customer_limit' => $data['perCustomerLimit'], 'is_active' => $data['isActive'], 'starts_at' => $data['startsAt'] ?? null, 'ends_at' => $data['endsAt'] ?? null];
    }

    private function data(Coupon $coupon): array
    {
        return ['id'=>(string) $coupon->id,'code'=>$coupon->code,'name'=>$coupon->name,'description'=>$coupon->description,'discountType'=>$coupon->discount_type,
            'value'=>$coupon->discount_type === 'fixed_amount' ? $coupon->value / 100 : $coupon->value,'minimumSpend'=>$coupon->min_spend !== null ? $coupon->min_spend / 100 : null,
            'usageLimit'=>$coupon->usage_limit,'usageCount'=>$coupon->usage_count,'perCustomerLimit'=>$coupon->per_customer_limit,'isActive'=>$coupon->is_active,
            'startsAt'=>($coupon->starts_at && $coupon->starts_at->year > 0 ? $coupon->starts_at->toIso8601String() : null),'endsAt'=>($coupon->ends_at && $coupon->ends_at->year > 0 ? $coupon->ends_at->toIso8601String() : null),'createdAt'=>$coupon->created_at?->toIso8601String(),'updatedAt'=>$coupon->updated_at?->toIso8601String()];
    }
}
