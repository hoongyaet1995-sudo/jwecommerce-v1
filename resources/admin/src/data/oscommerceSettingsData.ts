import {
  CustomOrderStatus,
  CountrySetting,
  ZoneSetting,
  CurrencySetting,
  OrderTotalModule,
  GeoTaxZone,
  TaxClass,
  TaxRate,
  GlobalTaxEngineSettings
} from '../types/oscommerceSettings';

export const INITIAL_CUSTOM_ORDER_STATUSES: CustomOrderStatus[] = [
  {
    id: 'status_pending',
    code: 'pending',
    name: 'Pending Payment',
    description: 'Order created by customer, awaiting gateway payment or manual bank transfer slip verification.',
    color: 'amber',
    isDefault: true,
    isDefaultPaid: false,
    isDefaultCancelled: false,
    notifyCustomer: true,
    publicVisibility: true,
    inventoryAction: 'reserve',
    emailTemplate: 'Order Placement Confirmation',
    whatsappTemplate: 'Hi {customer_name}, your order #{order_number} has been created. Total: {total_amount}. Click here to complete payment: {payment_link}',
    sortOrder: 10,
    ordersCount: 4
  },
  {
    id: 'status_processing',
    code: 'processing',
    name: 'Processing & Verified',
    description: 'Payment successfully captured. Order forwarded to warehouse picking & packing team.',
    color: 'indigo',
    isDefault: false,
    isDefaultPaid: true,
    isDefaultCancelled: false,
    notifyCustomer: true,
    publicVisibility: true,
    inventoryAction: 'deduct',
    emailTemplate: 'Payment Received & Order Processing',
    whatsappTemplate: 'Thank you {customer_name}! We received your payment for order #{order_number}. Our warehouse is now preparing your parcel.',
    sortOrder: 20,
    ordersCount: 12
  },
  {
    id: 'status_ready_to_ship',
    code: 'ready_to_ship',
    name: 'Packed & AWB Generated',
    description: 'Consignment note printed, parcel securely packaged and awaiting carrier vehicle pickup.',
    color: 'cyan',
    isDefault: false,
    isDefaultPaid: false,
    isDefaultCancelled: false,
    notifyCustomer: false,
    publicVisibility: true,
    inventoryAction: 'deduct',
    emailTemplate: 'Parcel Packed & Awaiting Courier',
    whatsappTemplate: 'Your order #{order_number} has been packed with AWB {tracking_number}. Courier pickup scheduled today.',
    sortOrder: 30,
    ordersCount: 6
  },
  {
    id: 'status_shipped',
    code: 'shipped',
    name: 'Dispatched (In Transit)',
    description: 'Courier scanned parcel into sorting hub. Tracking number active with live milestones.',
    color: 'blue',
    isDefault: false,
    isDefaultPaid: false,
    isDefaultCancelled: false,
    notifyCustomer: true,
    publicVisibility: true,
    inventoryAction: 'deduct',
    emailTemplate: 'Shipment Dispatched with Tracking',
    whatsappTemplate: 'Good news {customer_name}! Your order #{order_number} was dispatched via {courier_name}. Track live: {tracking_url}',
    sortOrder: 40,
    ordersCount: 18
  },
  {
    id: 'status_delivered',
    code: 'delivered',
    name: 'Delivered & Completed',
    description: 'Proof of Delivery (POD) signed by recipient. Loyalty rewards credited to customer account.',
    color: 'emerald',
    isDefault: false,
    isDefaultPaid: false,
    isDefaultCancelled: false,
    notifyCustomer: true,
    publicVisibility: true,
    inventoryAction: 'none',
    emailTemplate: 'Order Delivered & Review Request',
    whatsappTemplate: 'Order #{order_number} successfully delivered! You earned {points_earned} reward points. Please leave us a review!',
    sortOrder: 50,
    ordersCount: 85
  },
  {
    id: 'status_cancelled',
    code: 'cancelled',
    name: 'Cancelled & Restocked',
    description: 'Order voided due to customer request, non-payment timeout, or manual admin cancellation.',
    color: 'rose',
    isDefault: false,
    isDefaultPaid: false,
    isDefaultCancelled: true,
    notifyCustomer: true,
    publicVisibility: true,
    inventoryAction: 'restock',
    emailTemplate: 'Order Cancellation Notice',
    whatsappTemplate: 'Your order #{order_number} has been cancelled. If payment was made, refund processing has been initiated.',
    sortOrder: 60,
    ordersCount: 3
  },
  {
    id: 'status_on_hold',
    code: 'on_hold',
    name: 'On Hold (Review Required)',
    description: 'Fraud detection flag, out-of-stock item inquiry, or address clarification required.',
    color: 'purple',
    isDefault: false,
    isDefaultPaid: false,
    isDefaultCancelled: false,
    notifyCustomer: true,
    publicVisibility: false,
    inventoryAction: 'reserve',
    emailTemplate: 'Action Required on Your Order',
    whatsappTemplate: 'Hi {customer_name}, we need to verify your shipping address for order #{order_number}. Please reply to this message.',
    sortOrder: 70,
    ordersCount: 2
  }
];

export const INITIAL_COUNTRIES: CountrySetting[] = [
  {
    id: 'MY',
    name: 'Malaysia',
    isoCode2: 'MY',
    isoCode3: 'MYS',
    dialCode: '+60',
    currencyCode: 'MYR',
    addressFormat: '{street}\n{postcode} {city}\n{zone}, {country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: true,
    totalZones: 16,
    flagEmoji: '🇲🇾'
  },
  {
    id: 'SG',
    name: 'Singapore',
    isoCode2: 'SG',
    isoCode3: 'SGP',
    dialCode: '+65',
    currencyCode: 'SGD',
    addressFormat: '{street}\nSingapore {postcode}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: true,
    totalZones: 5,
    flagEmoji: '🇸🇬'
  },
  {
    id: 'ID',
    name: 'Indonesia',
    isoCode2: 'ID',
    isoCode3: 'IDN',
    dialCode: '+62',
    currencyCode: 'IDR',
    addressFormat: '{street}\n{city}, {zone} {postcode}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: true,
    totalZones: 34,
    flagEmoji: '🇮🇩'
  },
  {
    id: 'TH',
    name: 'Thailand',
    isoCode2: 'TH',
    isoCode3: 'THA',
    dialCode: '+66',
    currencyCode: 'THB',
    addressFormat: '{street}\n{city}, {zone} {postcode}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: true,
    totalZones: 76,
    flagEmoji: '🇹🇭'
  },
  {
    id: 'PH',
    name: 'Philippines',
    isoCode2: 'PH',
    isoCode3: 'PHL',
    dialCode: '+63',
    currencyCode: 'PHP',
    addressFormat: '{street}, {city}\n{postcode} {zone}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: false,
    totalZones: 17,
    flagEmoji: '🇵🇭'
  },
  {
    id: 'VN',
    name: 'Vietnam',
    isoCode2: 'VN',
    isoCode3: 'VNM',
    dialCode: '+84',
    currencyCode: 'VND',
    addressFormat: '{street}\n{city}, {zone} {postcode}\n{country}',
    postcodeRequired: false,
    isEnabled: true,
    isPopular: false,
    totalZones: 63,
    flagEmoji: '🇻🇳'
  },
  {
    id: 'BN',
    name: 'Brunei',
    isoCode2: 'BN',
    isoCode3: 'BRN',
    dialCode: '+673',
    currencyCode: 'BND',
    addressFormat: '{street}\n{city} {postcode}\n{zone}, {country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: false,
    totalZones: 4,
    flagEmoji: '🇧🇳'
  },
  {
    id: 'US',
    name: 'United States',
    isoCode2: 'US',
    isoCode3: 'USA',
    dialCode: '+1',
    currencyCode: 'USD',
    addressFormat: '{street}\n{city}, {zone} {postcode}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: true,
    totalZones: 50,
    flagEmoji: '🇺🇸'
  },
  {
    id: 'GB',
    name: 'United Kingdom',
    isoCode2: 'GB',
    isoCode3: 'GBR',
    dialCode: '+44',
    currencyCode: 'GBP',
    addressFormat: '{street}\n{city}\n{postcode}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: false,
    totalZones: 4,
    flagEmoji: '🇬🇧'
  },
  {
    id: 'AU',
    name: 'Australia',
    isoCode2: 'AU',
    isoCode3: 'AUS',
    dialCode: '+61',
    currencyCode: 'AUD',
    addressFormat: '{street}\n{city} {zone} {postcode}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: false,
    totalZones: 8,
    flagEmoji: '🇦🇺'
  },
  {
    id: 'JP',
    name: 'Japan',
    isoCode2: 'JP',
    isoCode3: 'JPN',
    dialCode: '+81',
    currencyCode: 'JPY',
    addressFormat: '〒{postcode}\n{zone} {city} {street}\n{country}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: false,
    totalZones: 47,
    flagEmoji: '🇯🇵'
  },
  {
    id: 'CN',
    name: 'China',
    isoCode2: 'CN',
    isoCode3: 'CHN',
    dialCode: '+86',
    currencyCode: 'CNY',
    addressFormat: '{country} {zone} {city}\n{street}\n{postcode}',
    postcodeRequired: true,
    isEnabled: true,
    isPopular: false,
    totalZones: 34,
    flagEmoji: '🇨🇳'
  }
];

export const INITIAL_ZONES: ZoneSetting[] = [
  // Malaysia 13 States + 3 Federal Territories
  {
    id: 'MY-SGR',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Selangor',
    zoneCode: 'SGR',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['40', '41', '42', '43', '44', '45', '46', '47', '48', '63', '64']
  },
  {
    id: 'MY-KUL',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'WP Kuala Lumpur',
    zoneCode: 'KUL',
    regionType: 'Federal Territory',
    shippingTier: 'Express Metro',
    isEnabled: true,
    postalPrefixes: ['50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60']
  },
  {
    id: 'MY-PJY',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'WP Putrajaya',
    zoneCode: 'PJY',
    regionType: 'Federal Territory',
    shippingTier: 'Express Metro',
    isEnabled: true,
    postalPrefixes: ['62']
  },
  {
    id: 'MY-JHR',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Johor',
    zoneCode: 'JHR',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['80', '81', '82', '83', '84', '85', '86']
  },
  {
    id: 'MY-PNG',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Penang (Pulau Pinang)',
    zoneCode: 'PNG',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['10', '11', '12', '13', '14']
  },
  {
    id: 'MY-PRK',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Perak',
    zoneCode: 'PRK',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['30', '31', '32', '33', '34', '35', '36', '39']
  },
  {
    id: 'MY-NSN',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Negeri Sembilan',
    zoneCode: 'NSN',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['70', '71', '72', '73']
  },
  {
    id: 'MY-MLK',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Melaka',
    zoneCode: 'MLK',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['75', '76', '77', '78']
  },
  {
    id: 'MY-PHG',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Pahang',
    zoneCode: 'PHG',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['25', '26', '27', '28', '39', '49', '69']
  },
  {
    id: 'MY-TRG',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Terengganu',
    zoneCode: 'TRG',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['20', '21', '22', '23', '24']
  },
  {
    id: 'MY-KTN',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Kelantan',
    zoneCode: 'KTN',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['15', '16', '17', '18']
  },
  {
    id: 'MY-KDH',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Kedah',
    zoneCode: 'KDH',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['05', '06', '07', '08', '09']
  },
  {
    id: 'MY-PLS',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Perlis',
    zoneCode: 'PLS',
    regionType: 'Peninsular Malaysia',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['01', '02']
  },
  {
    id: 'MY-SBH',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Sabah',
    zoneCode: 'SBH',
    regionType: 'East Malaysia (Borneo)',
    shippingTier: 'Air Freight East MY',
    isEnabled: true,
    postalPrefixes: ['88', '89', '90', '91']
  },
  {
    id: 'MY-SWK',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'Sarawak',
    zoneCode: 'SWK',
    regionType: 'East Malaysia (Borneo)',
    shippingTier: 'Air Freight East MY',
    isEnabled: true,
    postalPrefixes: ['93', '94', '95', '96', '97', '98']
  },
  {
    id: 'MY-LBN',
    countryId: 'MY',
    countryName: 'Malaysia',
    zoneName: 'WP Labuan',
    zoneCode: 'LBN',
    regionType: 'Federal Territory',
    shippingTier: 'Duty Free Port',
    isEnabled: true,
    postalPrefixes: ['87']
  },
  // Singapore Districts
  {
    id: 'SG-CTR',
    countryId: 'SG',
    countryName: 'Singapore',
    zoneName: 'Central District (CBD & Orchard)',
    zoneCode: 'SG-C',
    regionType: 'Singapore District',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['01', '02', '03', '04', '05', '06', '07', '08', '22', '23']
  },
  {
    id: 'SG-EST',
    countryId: 'SG',
    countryName: 'Singapore',
    zoneName: 'East District (Changi & Tampines)',
    zoneCode: 'SG-E',
    regionType: 'Singapore District',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['46', '47', '48', '49', '50', '51', '52']
  },
  {
    id: 'SG-WST',
    countryId: 'SG',
    countryName: 'Singapore',
    zoneName: 'West District (Jurong & Clementi)',
    zoneCode: 'SG-W',
    regionType: 'Singapore District',
    shippingTier: 'Standard Domestic',
    isEnabled: true,
    postalPrefixes: ['60', '61', '62', '63', '64', '65', '66', '67', '68']
  }
];

export const INITIAL_CURRENCIES: CurrencySetting[] = [
  {
    id: 'MYR',
    code: 'MYR',
    title: 'Malaysian Ringgit',
    symbolLeft: 'RM ',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 1.00000000,
    isDefault: true,
    autoUpdate: false,
    lastUpdated: '2026-08-17 08:00:00 (Base Currency)',
    isEnabled: true
  },
  {
    id: 'SGD',
    code: 'SGD',
    title: 'Singapore Dollar',
    symbolLeft: 'S$',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 0.30250000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (MAS FX Feed)',
    isEnabled: true
  },
  {
    id: 'USD',
    code: 'USD',
    title: 'US Dollar',
    symbolLeft: '$',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 0.22450000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (Oanda FX Feed)',
    isEnabled: true
  },
  {
    id: 'EUR',
    code: 'EUR',
    title: 'Euro',
    symbolLeft: '€',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 0.20850000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (ECB FX Feed)',
    isEnabled: true
  },
  {
    id: 'IDR',
    code: 'IDR',
    title: 'Indonesian Rupiah',
    symbolLeft: 'Rp ',
    symbolRight: '',
    decimalPlaces: 0,
    exchangeRate: 3580.00000000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (BI FX Feed)',
    isEnabled: true
  },
  {
    id: 'THB',
    code: 'THB',
    title: 'Thai Baht',
    symbolLeft: '฿',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 8.15000000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (BOT FX Feed)',
    isEnabled: true
  },
  {
    id: 'CNY',
    code: 'CNY',
    title: 'Chinese Yuan Renminbi',
    symbolLeft: '¥',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 1.62500000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (PBOC FX Feed)',
    isEnabled: true
  },
  {
    id: 'GBP',
    code: 'GBP',
    title: 'British Pound Sterling',
    symbolLeft: '£',
    symbolRight: '',
    decimalPlaces: 2,
    exchangeRate: 0.17800000,
    isDefault: false,
    autoUpdate: true,
    lastUpdated: '2026-08-17 06:30:15 (BOE FX Feed)',
    isEnabled: false
  }
];

export const INITIAL_ORDER_TOTAL_MODULES: OrderTotalModule[] = [
  {
    id: 'ot_1',
    code: 'ot_subtotal',
    title: 'Sub-Total',
    description: 'Calculates the base merchandise value of all line items in the cart before discounts and taxes.',
    sortOrder: 10,
    isEnabled: true,
    calculationType: 'additive',
    config: {}
  },
  {
    id: 'ot_2',
    code: 'ot_discount',
    title: 'Discount & Promo Coupons',
    description: 'Deducts voucher coupon codes, automatic bundle deals, or agent group volume wholesale discounts.',
    sortOrder: 20,
    isEnabled: true,
    calculationType: 'deductive',
    config: {
      allowDiscountsOnSaleItems: true
    }
  },
  {
    id: 'ot_3',
    code: 'ot_shipping',
    title: 'Shipping & Delivery Fee',
    description: 'Applies postage quoted by Pos Malaysia, Ninja Van, J&T, Flash, or free shipping threshold rules.',
    sortOrder: 30,
    isEnabled: true,
    calculationType: 'additive',
    config: {
      freeShippingThreshold: 150.00,
      applyTaxToShipping: false
    }
  },
  {
    id: 'ot_4',
    code: 'ot_tax',
    title: 'Sales & Service Tax (SST 8%)',
    description: 'Computes applicable Malaysian SST or regional tax rates dynamically based on customer delivery geo zone.',
    sortOrder: 40,
    isEnabled: true,
    calculationType: 'tax_computed',
    config: {
      applyTaxToShipping: false
    }
  },
  {
    id: 'ot_5',
    code: 'ot_reward_points',
    title: 'Reward Points Redemption',
    description: 'Converts shopper loyalty point balances into direct currency deduction at checkout (100 pts = RM 1.00).',
    sortOrder: 50,
    isEnabled: true,
    calculationType: 'deductive',
    config: {
      rewardRedemptionRate: 100
    }
  },
  {
    id: 'ot_6',
    code: 'ot_cod_fee',
    title: 'Cash on Delivery (COD) Surcharge',
    description: 'Adds extra handling and cash collection fee when recipient chooses COD cash payment at doorstep.',
    sortOrder: 60,
    isEnabled: true,
    calculationType: 'additive',
    config: {
      codHandlingFee: 5.00
    }
  },
  {
    id: 'ot_7',
    code: 'ot_rounding',
    title: 'BNM 5-Cent Rounding Mechanism',
    description: 'Bank Negara Malaysia mandatory 5-sen rounding rule for cash settlement and clean invoices.',
    sortOrder: 70,
    isEnabled: true,
    calculationType: 'rounding_rule',
    config: {
      roundingDirection: 'nearest_5_cents'
    }
  },
  {
    id: 'ot_8',
    code: 'ot_total',
    title: 'Grand Total',
    description: 'The final payable amount charged to customer credit card, FPX online banking, or e-wallet.',
    sortOrder: 100,
    isEnabled: true,
    calculationType: 'final_sum',
    config: {}
  }
];

export const INITIAL_GEO_TAX_ZONES: GeoTaxZone[] = [
  {
    id: 'geo_my_standard',
    name: 'Malaysia Domestic Standard SST Zone',
    description: 'All 13 States & Federal Territories of Malaysia subject to standard SST tax legislation.',
    countryIds: ['MY'],
    zoneIds: [], // All zones in Malaysia
    isActive: true
  },
  {
    id: 'geo_duty_free',
    name: 'Duty-Free Special Economic Zones',
    description: 'Labuan, Langkawi, Tioman free-port designated islands with tax exemptions.',
    countryIds: ['MY'],
    zoneIds: ['MY-LBN'],
    isActive: true
  },
  {
    id: 'geo_export_intl',
    name: 'International Cross-Border Export (0% Zero-Rated)',
    description: 'Overseas shipments to Singapore, US, EU, and Asia Pacific with 0% SST export treatment.',
    countryIds: ['SG', 'ID', 'TH', 'PH', 'VN', 'BN', 'US', 'GB', 'AU', 'JP', 'CN'],
    zoneIds: [],
    isActive: true
  }
];

export const INITIAL_TAX_CLASSES: TaxClass[] = [
  {
    id: 'tc_standard_goods',
    name: 'Taxable Goods (Standard 8%)',
    description: 'General consumer electronics, apparel, lifestyle accessories, and retail merchandise.',
    defaultRate: 8.0,
    productCount: 42
  },
  {
    id: 'tc_digital_services',
    name: 'Digital Services & Subscriptions (8%)',
    description: 'Software licenses, downloadable assets, e-gift cards, and SaaS extensions.',
    defaultRate: 8.0,
    productCount: 8
  },
  {
    id: 'tc_essential_food',
    name: 'Essential Groceries & Food (0% Zero-Rated)',
    description: 'Unprocessed agricultural produce, staple rice, cooking oil, and essential food items.',
    defaultRate: 0.0,
    productCount: 15
  },
  {
    id: 'tc_medical_exempt',
    name: 'Healthcare & Medical Devices (Exempt)',
    description: 'Approved prescription items and certified surgical supplies with SST exemption certificates.',
    defaultRate: 0.0,
    productCount: 5
  }
];

export const INITIAL_TAX_RATES: TaxRate[] = [
  {
    id: 'tr_1',
    taxClassId: 'tc_standard_goods',
    taxClassName: 'Taxable Goods (Standard 8%)',
    geoZoneId: 'geo_my_standard',
    geoZoneName: 'Malaysia Domestic Standard SST Zone',
    ratePercent: 8.0,
    description: 'Sales & Service Tax (SST 8.0%)',
    priority: 1,
    compound: false,
    isEnabled: true
  },
  {
    id: 'tr_2',
    taxClassId: 'tc_digital_services',
    taxClassName: 'Digital Services & Subscriptions (8%)',
    geoZoneId: 'geo_my_standard',
    geoZoneName: 'Malaysia Domestic Standard SST Zone',
    ratePercent: 8.0,
    description: 'Digital Service Tax (DST 8.0%)',
    priority: 1,
    compound: false,
    isEnabled: true
  },
  {
    id: 'tr_3',
    taxClassId: 'tc_standard_goods',
    taxClassName: 'Taxable Goods (Standard 8%)',
    geoZoneId: 'geo_duty_free',
    geoZoneName: 'Duty-Free Special Economic Zones',
    ratePercent: 0.0,
    description: 'Duty-Free Island Exemption (0.0%)',
    priority: 1,
    compound: false,
    isEnabled: true
  },
  {
    id: 'tr_4',
    taxClassId: 'tc_standard_goods',
    taxClassName: 'Taxable Goods (Standard 8%)',
    geoZoneId: 'geo_export_intl',
    geoZoneName: 'International Cross-Border Export (0% Zero-Rated)',
    ratePercent: 0.0,
    description: 'Zero-Rated Cross Border Export (0.0%)',
    priority: 1,
    compound: false,
    isEnabled: true
  },
  {
    id: 'tr_5',
    taxClassId: 'tc_essential_food',
    taxClassName: 'Essential Groceries & Food (0% Zero-Rated)',
    geoZoneId: 'geo_my_standard',
    geoZoneName: 'Malaysia Domestic Standard SST Zone',
    ratePercent: 0.0,
    description: 'Zero-Rated Essential Schedule (0.0%)',
    priority: 1,
    compound: false,
    isEnabled: true
  }
];

export const INITIAL_GLOBAL_TAX_ENGINE: GlobalTaxEngineSettings = {
  pricesIncludeTax: false, // Net pricing with tax added at checkout
  calculateTaxBasedOn: 'shipping_address',
  shippingTaxClassId: 'tc_standard_goods',
  displayTaxInCartSummary: 'single_line',
  taxExemptThreshold: 0
};
