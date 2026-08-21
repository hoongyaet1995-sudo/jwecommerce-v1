import { Promotion, PromotionUsageLog } from '../types/promotions';

export const INITIAL_PROMOTIONS: Promotion[] = [
  {
    id: 'promo-1',
    name: 'RAYA2026 Mega Storewide Voucher',
    description: 'Get RM 25 OFF with minimum spend of RM 150 across all collections.',
    promotionType: 'coupon_code',
    priority: 5,
    isStackable: false,
    stackingGroup: 'shop_voucher',
    applyExclusively: false,
    startsAt: '2026-08-01T00:00:00Z',
    endsAt: '2026-09-30T23:59:59Z',
    isActive: true,
    totalUsageLimit: 1000,
    totalUsageCount: 428,
    perUserLimit: 1,
    budgetLimitMyr: 25000,
    currentSpentMyr: 10700,
    coupons: [
      {
        id: 'cp-1',
        code: 'RAYA2026',
        usageLimit: 1000,
        usageCount: 428,
        isSingleUse: false,
        createdAt: '2026-08-01T00:00:00Z'
      }
    ],
    rules: [
      {
        id: 'r-1',
        type: 'min_order_subtotal',
        operator: '>=',
        value: 150,
        description: 'Minimum basket subtotal of RM 150.00'
      }
    ],
    action: {
      actionType: 'fixed_discount',
      discountAmount: 25
    },
    target: 'order_subtotal',
    createdAt: '2026-08-01T00:00:00Z',
    updatedAt: '2026-08-15T12:00:00Z'
  },
  {
    id: 'promo-2',
    name: 'Streetwear Graphic Tees: Any 3 for RM 99',
    description: 'Mix & Match any 3 oversized cotton t-shirts from Streetwear Collection for only RM 99.',
    promotionType: 'mix_and_match',
    priority: 2,
    isStackable: true,
    stackingGroup: 'shop_voucher',
    applyExclusively: false,
    startsAt: '2026-08-10T00:00:00Z',
    endsAt: '2026-08-31T23:59:59Z',
    isActive: true,
    totalUsageLimit: 500,
    totalUsageCount: 164,
    perUserLimit: 3,
    budgetLimitMyr: 15000,
    currentSpentMyr: 6232,
    rules: [
      {
        id: 'r-2a',
        type: 'collection_membership',
        operator: 'IN',
        value: ['col-streetwear', 'col-summer'],
        description: 'Applies to items in Streetwear & Summer Collections'
      },
      {
        id: 'r-2b',
        type: 'min_quantity',
        operator: '>=',
        value: 3,
        description: 'Requires at least 3 qualifying collection items'
      }
    ],
    action: {
      actionType: 'bundle_fixed_price',
      bundleSize: 3,
      bundleFixedPrice: 99,
      overflowBehavior: 'charge_standard_unit_price'
    },
    target: 'matched_items',
    createdAt: '2026-08-10T00:00:00Z',
    updatedAt: '2026-08-17T09:30:00Z'
  },
  {
    id: 'promo-3',
    name: 'Buy Heavyweight Hoodie, Get Socks for RM 1',
    description: 'Add-on special: Purchase 450GSM French Terry Hoodie and unlock premium ribbed socks for just RM 1.',
    promotionType: 'bundle_addon',
    priority: 3,
    isStackable: true,
    stackingGroup: 'shop_voucher',
    applyExclusively: false,
    startsAt: '2026-08-01T00:00:00Z',
    endsAt: '2026-10-31T23:59:59Z',
    isActive: true,
    totalUsageLimit: 300,
    totalUsageCount: 89,
    perUserLimit: 2,
    budgetLimitMyr: 5000,
    currentSpentMyr: 1869,
    rules: [
      {
        id: 'r-3',
        type: 'min_quantity',
        operator: '>=',
        value: 1,
        description: 'Requires purchasing Heavyweight Hoodie (SKU: HD-450GSM)'
      }
    ],
    action: {
      actionType: 'addon_fixed_price',
      addonSku: 'ACC-SOCK-01',
      addonPrice: 1
    },
    target: 'custom_sku',
    createdAt: '2026-08-01T00:00:00Z',
    updatedAt: '2026-08-12T10:00:00Z'
  },
  {
    id: 'promo-4',
    name: 'Buy 1 Get 1 Free: Baseball Caps & Beanies',
    description: 'BOGO Offer: Buy any selected cap or beanie and receive the second item for free.',
    promotionType: 'bogo',
    priority: 1,
    isStackable: false,
    stackingGroup: 'shop_voucher',
    applyExclusively: false,
    startsAt: '2026-08-15T00:00:00Z',
    endsAt: '2026-08-25T23:59:59Z',
    isActive: true,
    totalUsageLimit: 200,
    totalUsageCount: 78,
    perUserLimit: 1,
    budgetLimitMyr: 6000,
    currentSpentMyr: 2340,
    rules: [
      {
        id: 'r-4',
        type: 'collection_membership',
        operator: 'IN',
        value: ['col-accessories'],
        description: 'Applies to Headwear & Accessories'
      }
    ],
    action: {
      actionType: 'free_item',
      freeSku: 'ACC-CAP-09',
      freeSkuName: 'Vintage Washed Dad Cap'
    },
    target: 'cheapest_item',
    createdAt: '2026-08-15T00:00:00Z',
    updatedAt: '2026-08-16T14:20:00Z'
  },
  {
    id: 'promo-5',
    name: 'VIP Member Tiered Discount (Up to 20% OFF)',
    description: 'Spend RM 200 get 10% off, spend RM 350 get 15% off, spend RM 500 get 20% off.',
    promotionType: 'quantity_tier',
    priority: 4,
    isStackable: false,
    stackingGroup: 'shop_voucher',
    applyExclusively: false,
    startsAt: '2026-08-01T00:00:00Z',
    endsAt: '2026-12-31T23:59:59Z',
    isActive: true,
    totalUsageLimit: null,
    totalUsageCount: 312,
    perUserLimit: 10,
    budgetLimitMyr: 50000,
    currentSpentMyr: 14820,
    rules: [
      {
        id: 'r-5',
        type: 'min_order_subtotal',
        operator: '>=',
        value: 200,
        description: 'Tier 1 starting at RM 200 min basket spend'
      }
    ],
    action: {
      actionType: 'percentage_discount',
      discountPercentage: 15,
      maxDiscountCap: 75
    },
    target: 'order_subtotal',
    createdAt: '2026-08-01T00:00:00Z',
    updatedAt: '2026-08-17T08:15:00Z'
  },
  {
    id: 'promo-6',
    name: 'FPX & GrabPay 5% Coins Cashback',
    description: 'Pay via Malaysian Online Banking (FPX) or GrabPay to earn 5% Store Loyalty Coins (Capped at 500 coins = RM 5.00).',
    promotionType: 'cashback_coins',
    priority: 8,
    isStackable: true,
    stackingGroup: 'payment_promo',
    applyExclusively: false,
    startsAt: '2026-08-01T00:00:00Z',
    endsAt: '2026-12-31T23:59:59Z',
    isActive: true,
    totalUsageLimit: null,
    totalUsageCount: 890,
    perUserLimit: 50,
    budgetLimitMyr: 10000,
    currentSpentMyr: 3450,
    rules: [
      {
        id: 'r-6a',
        type: 'payment_method',
        operator: 'IN',
        value: ['FPX', 'GrabPay', 'TouchNGo'],
        description: 'Valid for FPX / E-Wallet gateways only'
      },
      {
        id: 'r-6b',
        type: 'min_order_subtotal',
        operator: '>=',
        value: 80,
        description: 'Min order subtotal RM 80'
      }
    ],
    action: {
      actionType: 'cashback_points',
      cashbackPercentage: 5,
      maxCoins: 500
    },
    target: 'order_subtotal',
    createdAt: '2026-08-01T00:00:00Z',
    updatedAt: '2026-08-17T11:00:00Z'
  }
];

export const INITIAL_PROMOTION_USAGES: PromotionUsageLog[] = [
  {
    id: 'usg-1',
    promotionId: 'promo-1',
    promotionName: 'RAYA2026 Mega Storewide Voucher',
    couponCode: 'RAYA2026',
    orderNumber: 'ORD-9842',
    customerName: 'Muhammad Farhan',
    customerEmail: 'farhan.m@gmail.com',
    discountAmount: 25.00,
    cashbackCoinsIssued: 0,
    status: 'settled',
    createdAt: '2026-08-17T14:22:10Z'
  },
  {
    id: 'usg-2',
    promotionId: 'promo-2',
    promotionName: 'Streetwear Graphic Tees: Any 3 for RM 99',
    orderNumber: 'ORD-9840',
    customerName: 'Tan Wei Ling',
    customerEmail: 'weiling.tan@hotmail.com',
    discountAmount: 38.00,
    cashbackCoinsIssued: 0,
    status: 'settled',
    createdAt: '2026-08-17T11:15:30Z'
  },
  {
    id: 'usg-3',
    promotionId: 'promo-6',
    promotionName: 'FPX & GrabPay 5% Coins Cashback',
    orderNumber: 'ORD-9839',
    customerName: 'Siti Nurhaliza',
    customerEmail: 'siti.nur@yahoo.com',
    discountAmount: 0.00,
    cashbackCoinsIssued: 450,
    status: 'applied',
    createdAt: '2026-08-17T09:40:12Z'
  },
  {
    id: 'usg-4',
    promotionId: 'promo-3',
    promotionName: 'Buy Heavyweight Hoodie, Get Socks for RM 1',
    orderNumber: 'ORD-9835',
    customerName: 'Kavitha Raj',
    customerEmail: 'kavitha.r@outlook.com',
    discountAmount: 28.00,
    cashbackCoinsIssued: 0,
    status: 'settled',
    createdAt: '2026-08-16T18:05:44Z'
  }
];
