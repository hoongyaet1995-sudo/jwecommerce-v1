import type { LaravelAdminBootstrap } from '../types';
import type { Promotion, PromotionUsageLog } from '../types/promotions';

interface CouponRecord {
  id: string; code: string; name: string; description?: string; discountType: 'percentage' | 'fixed_amount' | 'free_shipping'; value: number;
  minimumSpend: number | null; usageLimit: number | null; usageCount: number; perCustomerLimit: number; isActive: boolean;
  startsAt: string | null; endsAt: string | null; createdAt: string; updatedAt: string;
}

type CouponResponse = { coupons: CouponRecord[]; usages: PromotionUsageLog[] };

const toPromotion = (coupon: CouponRecord): Promotion => ({
  id: coupon.id, name: coupon.name || coupon.code, description: coupon.description || '', promotionType: 'coupon_code', priority: 1,
  isStackable: false, stackingGroup: coupon.discountType === 'free_shipping' ? 'shipping_waiver' : 'shop_voucher', applyExclusively: false,
  startsAt: coupon.startsAt ?? coupon.createdAt, endsAt: coupon.endsAt ?? '2099-12-31T23:59:59.000Z', isActive: coupon.isActive,
  totalUsageLimit: coupon.usageLimit, totalUsageCount: coupon.usageCount, perUserLimit: coupon.perCustomerLimit, budgetLimitMyr: null,
  currentSpentMyr: 0, coupons: [{ id: `coupon-${coupon.id}`, code: coupon.code, usageLimit: coupon.usageLimit, usageCount: coupon.usageCount, isSingleUse: coupon.perCustomerLimit === 1, createdAt: coupon.createdAt }],
  rules: coupon.minimumSpend !== null ? [{ id: `min-${coupon.id}`, type: 'min_order_subtotal', operator: '>=', value: coupon.minimumSpend, description: `Minimum basket subtotal of RM ${coupon.minimumSpend.toFixed(2)}` }] : [],
  action: coupon.discountType === 'percentage' ? { actionType: 'percentage_discount', discountPercentage: coupon.value } : coupon.discountType === 'free_shipping' ? { actionType: 'free_shipping' } : { actionType: 'fixed_discount', discountAmount: coupon.value },
  target: coupon.discountType === 'free_shipping' ? 'shipping_fee' : 'order_subtotal', createdAt: coupon.createdAt, updatedAt: coupon.updatedAt,
});

const requestPayload = (promotion: Promotion) => {
  if (promotion.promotionType !== 'coupon_code' || !promotion.coupons?.[0]) throw new Error('P0 operational promotions currently support coupon-code campaigns only.');
  const discountType = promotion.action.actionType === 'percentage_discount' ? 'percentage' : promotion.action.actionType === 'free_shipping' ? 'free_shipping' : 'fixed_amount';
  return { code: promotion.coupons[0].code, name: promotion.name, description: promotion.description, discountType,
    value: discountType === 'percentage' ? promotion.action.discountPercentage ?? 0 : discountType === 'fixed_amount' ? promotion.action.discountAmount ?? 0 : 0,
    minimumSpend: promotion.rules.find(rule => rule.type === 'min_order_subtotal')?.value ?? null, usageLimit: promotion.totalUsageLimit,
    perCustomerLimit: promotion.perUserLimit || 1, isActive: promotion.isActive, startsAt: promotion.startsAt, endsAt: promotion.endsAt };
};

export const fetchCoupons = async (bootstrap: LaravelAdminBootstrap): Promise<{ promotions: Promotion[]; usages: PromotionUsageLog[] }> => {
  if (!bootstrap.couponsUrl) throw new Error('Coupon API is unavailable.');
  const response = await window.fetch(bootstrap.couponsUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Coupon data request failed.');
  const payload = await response.json() as CouponResponse;
  return { promotions: payload.coupons.map(toPromotion), usages: Array.isArray(payload.usages) ? payload.usages : [] };
};

export const saveCouponPromotion = async (bootstrap: LaravelAdminBootstrap, promotion: Promotion): Promise<Promotion> => {
  if (!bootstrap.couponsUrl) throw new Error('Coupon API is unavailable.');
  const persisted = /^\d+$/.test(promotion.id);
  const response = await window.fetch(`${bootstrap.couponsUrl}${persisted ? `/${promotion.id}` : ''}`, { method: persisted ? 'PATCH' : 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken }, body: JSON.stringify(requestPayload(promotion)) });
  const payload = await response.json().catch(() => null) as { data?: CouponRecord; message?: string; errors?: Record<string, string[]> } | null;
  if (!response.ok || !payload?.data) throw new Error(payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? 'Coupon save failed.');
  return toPromotion(payload.data);
};

export const deleteCouponPromotion = async (bootstrap: LaravelAdminBootstrap, id: string): Promise<void> => {
  if (!bootstrap.couponsUrl) throw new Error('Coupon API is unavailable.');
  const response = await window.fetch(`${bootstrap.couponsUrl}/${encodeURIComponent(id)}`, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': bootstrap.csrfToken } });
  if (!response.ok && response.status !== 204) throw new Error('Coupon delete failed.');
};
