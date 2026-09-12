export type PromotionType = 
  | 'coupon_code'
  | 'quantity_tier'
  | 'bundle_addon'
  | 'mix_and_match'
  | 'bogo'
  | 'cashback_coins';

export type DiscountTarget = 
  | 'order_subtotal'
  | 'cheapest_item'
  | 'most_expensive_item'
  | 'matched_items'
  | 'shipping_fee'
  | 'custom_sku';

export type StackingGroup = 
  | 'shop_voucher'
  | 'platform_voucher'
  | 'payment_promo'
  | 'shipping_waiver';

export interface CouponItem {
  id: string;
  code: string;
  usageLimit: number | null;
  usageCount: number;
  isSingleUse: boolean;
  assignedCustomerId?: string;
  createdAt: string;
}

export interface PromotionRuleCondition {
  id: string;
  type: 'min_order_subtotal' | 'min_quantity' | 'collection_membership' | 'customer_tier' | 'channel_match' | 'payment_method';
  operator: '>=' | '<=' | 'IN' | 'EQUALS';
  value: any;
  description: string;
}

export interface PromotionActionConfig {
  actionType: 
    | 'fixed_discount'
    | 'percentage_discount'
    | 'bundle_fixed_price'
    | 'free_item'
    | 'addon_fixed_price'
    | 'cashback_points'
    | 'free_shipping';
  discountAmount?: number;
  discountPercentage?: number;
  maxDiscountCap?: number;
  bundleSize?: number;
  bundleFixedPrice?: number;
  freeSku?: string;
  freeSkuName?: string;
  addonSku?: string;
  addonPrice?: number;
  cashbackPercentage?: number;
  maxCoins?: number;
  overflowBehavior?: 'charge_standard_unit_price' | 'pro_rate';
}

export interface Promotion {
  id: string;
  name: string;
  description: string;
  promotionType: PromotionType;
  priority: number; // 1 = highest
  isStackable: boolean;
  stackingGroup: StackingGroup;
  applyExclusively: boolean;
  startsAt: string;
  endsAt: string;
  isActive: boolean;
  totalUsageLimit: number | null;
  totalUsageCount: number;
  perUserLimit: number;
  budgetLimitMyr: number | null;
  currentSpentMyr: number;
  coupons?: CouponItem[];
  rules: PromotionRuleCondition[];
  action: PromotionActionConfig;
  target: DiscountTarget;
  createdAt: string;
  updatedAt: string;
}

export interface PromotionUsageLog {
  id: string;
  promotionId: string;
  promotionName: string;
  couponCode?: string;
  orderNumber: string;
  customerName: string;
  customerEmail: string;
  discountAmount: number;
  cashbackCoinsIssued: number;
  status: 'applied' | 'settled' | 'reverted';
  createdAt: string;
}
