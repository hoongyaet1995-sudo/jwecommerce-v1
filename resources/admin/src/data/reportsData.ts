import {
  MetricCardData,
  DailySalesDataPoint,
  ChannelPerformance,
  RegionalSalesBreakdown,
  OrderFunnelStage,
  ProductPerformanceReport,
  CustomerCohortData,
  RFMSegmentData,
  CourierSlaReport,
  MarketingCampaignPerformance,
  TaxReportSummary,
  PaymentGatewayFeeReport
} from '../types/reports';

export const EXECUTIVE_OVERVIEW_METRICS: MetricCardData[] = [
  {
    id: 'gmv',
    label: 'Gross Merchandise Value (GMV)',
    value: 'RM 148,920.00',
    numericValue: 148920,
    changePercent: 18.6,
    trend: 'up',
    comparisonPeriod: 'vs. previous 30 days (RM 125,560.00)',
    subtext: '3,482 confirmed orders across 5 channels',
    sparklineData: [28, 35, 42, 38, 48, 56, 52, 64, 70, 68, 78, 85, 92]
  },
  {
    id: 'net_sales',
    label: 'Net Revenue (After Refunds & Discs)',
    value: 'RM 136,250.40',
    numericValue: 136250.4,
    changePercent: 16.2,
    trend: 'up',
    comparisonPeriod: 'vs. previous 30 days',
    subtext: 'RM 4,820.00 refunds (3.2% RMA rate)',
    sparklineData: [26, 32, 39, 35, 44, 52, 49, 60, 65, 62, 72, 79, 86]
  },
  {
    id: 'gross_profit',
    label: 'Gross Profit & Margin',
    value: 'RM 68,125.20',
    numericValue: 68125.2,
    changePercent: 19.4,
    trend: 'up',
    comparisonPeriod: '50.0% Gross Margin',
    subtext: 'Total COGS: RM 68,125.20',
    sparklineData: [13, 16, 20, 18, 22, 26, 25, 30, 33, 31, 36, 40, 43]
  },
  {
    id: 'aov',
    label: 'Average Order Value (AOV)',
    value: 'RM 42.77',
    numericValue: 42.77,
    changePercent: 4.8,
    trend: 'up',
    comparisonPeriod: 'vs. last month (RM 40.81)',
    subtext: 'Highest AOV on Web Direct (RM 68.50)',
    sparklineData: [40, 39, 41, 42, 41, 43, 44, 42, 43, 45, 43, 44, 43]
  },
  {
    id: 'repurchase_rate',
    label: 'Returning Customer Rate',
    value: '34.2%',
    numericValue: 34.2,
    changePercent: 3.1,
    trend: 'up',
    comparisonPeriod: 'vs. last month (31.1%)',
    subtext: '1,191 repeat buyers in cohort',
    sparklineData: [30, 31, 31, 32, 32, 33, 34, 33, 34, 34, 35, 34, 34]
  },
  {
    id: 'cod_remittance',
    label: 'COD Settlement & Accuracy',
    value: 'RM 22,480.00',
    numericValue: 22480,
    changePercent: 99.4,
    trend: 'neutral',
    comparisonPeriod: '99.4% Collection rate',
    subtext: 'RM 420.00 pending courier bank-in',
    sparklineData: [18, 19, 21, 20, 22, 23, 21, 24, 25, 23, 24, 22, 22]
  }
];

export const DAILY_SALES_TREND: DailySalesDataPoint[] = [
  { date: '2026-08-01', dayName: 'Sat', gmv: 4200, netSales: 3950, ordersCount: 98, aov: 42.8, cogs: 2100, grossProfit: 1850, webSales: 1800, tiktokSales: 1200, shopeeSales: 800, lazadaSales: 400 },
  { date: '2026-08-02', dayName: 'Sun', gmv: 5400, netSales: 5100, ordersCount: 126, aov: 42.8, cogs: 2600, grossProfit: 2500, webSales: 2400, tiktokSales: 1600, shopeeSales: 900, lazadaSales: 500 },
  { date: '2026-08-03', dayName: 'Mon', gmv: 4800, netSales: 4500, ordersCount: 112, aov: 42.8, cogs: 2350, grossProfit: 2150, webSales: 2100, tiktokSales: 1400, shopeeSales: 850, lazadaSales: 450 },
  { date: '2026-08-04', dayName: 'Tue', gmv: 4600, netSales: 4300, ordersCount: 108, aov: 42.6, cogs: 2250, grossProfit: 2050, webSales: 1950, tiktokSales: 1350, shopeeSales: 820, lazadaSales: 480 },
  { date: '2026-08-05', dayName: 'Wed', gmv: 5100, netSales: 4800, ordersCount: 119, aov: 42.9, cogs: 2500, grossProfit: 2300, webSales: 2200, tiktokSales: 1500, shopeeSales: 900, lazadaSales: 500 },
  { date: '2026-08-06', dayName: 'Thu', gmv: 5300, netSales: 5000, ordersCount: 123, aov: 43.1, cogs: 2580, grossProfit: 2420, webSales: 2300, tiktokSales: 1550, shopeeSales: 950, lazadaSales: 500 },
  { date: '2026-08-07', dayName: 'Fri', gmv: 6200, netSales: 5850, ordersCount: 144, aov: 43.0, cogs: 3000, grossProfit: 2850, webSales: 2700, tiktokSales: 1850, shopeeSales: 1100, lazadaSales: 550 },
  { date: '2026-08-08', dayName: 'Sat (8.8 Mega)', gmv: 12800, netSales: 11900, ordersCount: 295, aov: 43.4, cogs: 6100, grossProfit: 5800, webSales: 4800, tiktokSales: 4200, shopeeSales: 2400, lazadaSales: 1400 },
  { date: '2026-08-09', dayName: 'Sun', gmv: 8400, netSales: 7800, ordersCount: 194, aov: 43.3, cogs: 4100, grossProfit: 3700, webSales: 3500, tiktokSales: 2600, shopeeSales: 1500, lazadaSales: 800 },
  { date: '2026-08-10', dayName: 'Mon', gmv: 4900, netSales: 4600, ordersCount: 114, aov: 43.0, cogs: 2400, grossProfit: 2200, webSales: 2150, tiktokSales: 1450, shopeeSales: 850, lazadaSales: 450 },
  { date: '2026-08-11', dayName: 'Tue', gmv: 4750, netSales: 4450, ordersCount: 110, aov: 43.2, cogs: 2320, grossProfit: 2130, webSales: 2050, tiktokSales: 1400, shopeeSales: 820, lazadaSales: 480 },
  { date: '2026-08-12', dayName: 'Wed', gmv: 5200, netSales: 4900, ordersCount: 121, aov: 43.0, cogs: 2550, grossProfit: 2350, webSales: 2250, tiktokSales: 1520, shopeeSales: 910, lazadaSales: 520 },
  { date: '2026-08-13', dayName: 'Thu', gmv: 5600, netSales: 5250, ordersCount: 129, aov: 43.4, cogs: 2750, grossProfit: 2500, webSales: 2450, tiktokSales: 1650, shopeeSales: 980, lazadaSales: 520 },
  { date: '2026-08-14', dayName: 'Fri (Payday Promo)', gmv: 7800, netSales: 7300, ordersCount: 178, aov: 43.8, cogs: 3800, grossProfit: 3500, webSales: 3400, tiktokSales: 2350, shopeeSales: 1350, lazadaSales: 700 },
  { date: '2026-08-15', dayName: 'Sat', gmv: 6900, netSales: 6450, ordersCount: 159, aov: 43.4, cogs: 3350, grossProfit: 3100, webSales: 2950, tiktokSales: 2100, shopeeSales: 1200, lazadaSales: 650 },
  { date: '2026-08-16', dayName: 'Sun', gmv: 6100, netSales: 5700, ordersCount: 141, aov: 43.3, cogs: 2980, grossProfit: 2720, webSales: 2600, tiktokSales: 1850, shopeeSales: 1080, lazadaSales: 570 },
  { date: '2026-08-17', dayName: 'Today (Live)', gmv: 5900, netSales: 5500, ordersCount: 136, aov: 43.4, cogs: 2880, grossProfit: 2620, webSales: 2550, tiktokSales: 1780, shopeeSales: 1020, lazadaSales: 550 }
];

export const CHANNEL_PERFORMANCE_LIST: ChannelPerformance[] = [
  {
    channel: 'JW Direct Storefront (Web & Mobile)',
    channelKey: 'web_direct',
    orders: 1420,
    gmv: 68450.00,
    netRevenue: 64800.00,
    aov: 48.20,
    conversionRate: 3.82,
    sharePercent: 46.0,
    growthWoW: 14.5,
    color: '#6366f1' // Indigo
  },
  {
    channel: 'TikTok Shop Malaysia (Live & Creator)',
    channelKey: 'tiktok_shop',
    orders: 1140,
    gmv: 43620.00,
    netRevenue: 39800.00,
    aov: 38.26,
    conversionRate: 4.95,
    sharePercent: 29.3,
    growthWoW: 28.2,
    color: '#00f2fe' // Cyan / TikTok
  },
  {
    channel: 'Shopee Official Mall',
    channelKey: 'shopee',
    orders: 620,
    gmv: 24150.00,
    netRevenue: 21950.00,
    aov: 38.95,
    conversionRate: 2.75,
    sharePercent: 16.2,
    growthWoW: 8.4,
    color: '#ee4d2d' // Orange / Shopee
  },
  {
    channel: 'Lazada LazMall Store',
    channelKey: 'lazada',
    orders: 220,
    gmv: 9200.00,
    netRevenue: 8300.40,
    aov: 41.81,
    conversionRate: 2.10,
    sharePercent: 6.2,
    growthWoW: 4.1,
    color: '#0f146d' // Deep Blue / Lazada
  },
  {
    channel: 'WhatsApp Social B2B & Chat',
    channelKey: 'whatsapp_b2b',
    orders: 82,
    gmv: 3500.00,
    netRevenue: 3400.00,
    aov: 42.68,
    conversionRate: 18.5,
    sharePercent: 2.3,
    growthWoW: 22.0,
    color: '#22c55e' // Green / WhatsApp
  }
];

export const REGIONAL_SALES_DATA: RegionalSalesBreakdown[] = [
  { region: 'Peninsular', state: 'Selangor & KL (Klang Valley)', orders: 1680, gmv: 74500.00, sharePercent: 50.0, topCourier: 'Pos Malaysia Express', avgDeliveryDays: 1.2 },
  { region: 'Peninsular', state: 'Johor (Southern Hub)', orders: 540, gmv: 23100.00, sharePercent: 15.5, topCourier: 'J&T Express', avgDeliveryDays: 1.4 },
  { region: 'Peninsular', state: 'Penang & Northern Region', orders: 460, gmv: 19800.00, sharePercent: 13.3, topCourier: 'Ninja Van Malaysia', avgDeliveryDays: 1.5 },
  { region: 'Peninsular', state: 'Perak, Melaka & Negeri Sembilan', orders: 340, gmv: 14200.00, sharePercent: 9.5, topCourier: 'Flash Express', avgDeliveryDays: 1.8 },
  { region: 'Peninsular', state: 'Pahang, Terengganu, Kelantan (East Coast)', orders: 240, gmv: 9800.00, sharePercent: 6.6, topCourier: 'Pos Malaysia (Air/Laju)', avgDeliveryDays: 2.1 },
  { region: 'East Malaysia', state: 'Sabah (Kota Kinabalu, Sandakan)', orders: 120, gmv: 4320.00, sharePercent: 2.9, topCourier: 'EasyParcel Borneo Freight', avgDeliveryDays: 3.4 },
  { region: 'East Malaysia', state: 'Sarawak (Kuching, Miri)', orders: 102, gmv: 3200.00, sharePercent: 2.2, topCourier: 'Pos Malaysia Air Parcel', avgDeliveryDays: 3.2 }
];

export const ORDER_FUNNEL_STAGES: OrderFunnelStage[] = [
  { stage: '1. Order Placed & Payment Auth', count: 3580, percentage: 100, avgTimeSpentHours: 0.1, dropOffRate: 2.7 },
  { stage: '2. Payment Verified & Fraud Pass', count: 3482, percentage: 97.3, avgTimeSpentHours: 0.2, dropOffRate: 0.5 },
  { stage: '3. Pick & Pack at Warehouse', count: 3465, percentage: 96.8, avgTimeSpentHours: 2.4, dropOffRate: 0.2 },
  { stage: '4. Thermal AWB Generated', count: 3458, percentage: 96.6, avgTimeSpentHours: 0.4, dropOffRate: 0.1 },
  { stage: '5. Courier Manifest & Dispatched', count: 3454, percentage: 96.5, avgTimeSpentHours: 4.8, dropOffRate: 0.3 },
  { stage: '6. In-Transit & Hub Sorting', count: 3443, percentage: 96.2, avgTimeSpentHours: 18.2, dropOffRate: 0.8 },
  { stage: '7. Out for Delivery (Courier Bike/Van)', count: 3415, percentage: 95.4, avgTimeSpentHours: 3.6, dropOffRate: 1.4 },
  { stage: '8. Successfully Delivered / COD Paid', count: 3367, percentage: 94.0, avgTimeSpentHours: 0.0 }
];

export const PRODUCT_PERFORMANCE_LIST: ProductPerformanceReport[] = [
  {
    sku: 'JW-TEE-280-BLK',
    name: 'JW Studio 280GSM Heavyweight Luxury Tee (Jet Black)',
    category: 'Apparel',
    unitsSold: 642,
    revenue: 35310.00,
    cogs: 14124.00,
    grossMarginPercent: 60.0,
    currentStock: 184,
    daysOfInventory: 9,
    stockStatus: 'low_stock',
    returnRate: 1.2,
    velocityScore: 'Fast'
  },
  {
    sku: 'JW-TEE-280-WHT',
    name: 'JW Studio 280GSM Heavyweight Luxury Tee (Chalk White)',
    category: 'Apparel',
    unitsSold: 580,
    revenue: 31900.00,
    cogs: 12760.00,
    grossMarginPercent: 60.0,
    currentStock: 320,
    daysOfInventory: 17,
    stockStatus: 'in_stock',
    returnRate: 1.4,
    velocityScore: 'Fast'
  },
  {
    sku: 'JW-HD-460-CHA',
    name: 'JW Studio Oversized French Terry Hoodie (Washed Charcoal)',
    category: 'Outerwear',
    unitsSold: 410,
    revenue: 36490.00,
    cogs: 16420.50,
    grossMarginPercent: 55.0,
    currentStock: 86,
    daysOfInventory: 6,
    stockStatus: 'low_stock',
    returnRate: 2.1,
    velocityScore: 'Fast'
  },
  {
    sku: 'JW-CAP-VNT-KHA',
    name: 'JW Studio Unstructured Vintage Dad Cap (Khaki Twill)',
    category: 'Accessories',
    unitsSold: 380,
    revenue: 14820.00,
    cogs: 5187.00,
    grossMarginPercent: 65.0,
    currentStock: 450,
    daysOfInventory: 36,
    stockStatus: 'in_stock',
    returnRate: 0.5,
    velocityScore: 'Normal'
  },
  {
    sku: 'JW-TOT-HEV-NAT',
    name: 'JW Studio Heavyweight Canvas Tote Bag (Natural Ecru)',
    category: 'Accessories',
    unitsSold: 290,
    revenue: 8410.00,
    cogs: 2943.50,
    grossMarginPercent: 65.0,
    currentStock: 12,
    daysOfInventory: 1,
    stockStatus: 'low_stock',
    returnRate: 0.3,
    velocityScore: 'Fast'
  },
  {
    sku: 'JW-SKS-3PK-WHT',
    name: 'JW Studio Ribbed Combed Cotton Crew Socks (3-Pack)',
    category: 'Accessories',
    unitsSold: 210,
    revenue: 5250.00,
    cogs: 1837.50,
    grossMarginPercent: 65.0,
    currentStock: 620,
    daysOfInventory: 88,
    stockStatus: 'overstocked',
    returnRate: 0.0,
    velocityScore: 'Slow'
  },
  {
    sku: 'JW-ACC-PIN-SLV',
    name: 'JW Studio Solid Brass Enamel Pin (Silver Chrome Edition)',
    category: 'Limited Collectibles',
    unitsSold: 18,
    revenue: 450.00,
    cogs: 180.00,
    grossMarginPercent: 60.0,
    currentStock: 240,
    daysOfInventory: 400,
    stockStatus: 'overstocked',
    returnRate: 0.0,
    velocityScore: 'Dead'
  }
];

export const CUSTOMER_COHORT_MATRIX: CustomerCohortData[] = [
  { cohortMonth: '2026-03', initialCustomers: 450, m0: 100, m1: 34.2, m2: 28.4, m3: 24.1, m4: 21.0, m5: 19.8 },
  { cohortMonth: '2026-04', initialCustomers: 520, m0: 100, m1: 36.5, m2: 29.8, m3: 25.4, m4: 22.1, m5: 0 },
  { cohortMonth: '2026-05', initialCustomers: 610, m0: 100, m1: 38.1, m2: 31.2, m3: 27.0, m4: 0, m5: 0 },
  { cohortMonth: '2026-06', initialCustomers: 740, m0: 100, m1: 39.4, m2: 32.5, m3: 0, m4: 0, m5: 0 },
  { cohortMonth: '2026-07', initialCustomers: 890, m0: 100, m1: 41.2, m2: 0, m3: 0, m4: 0, m5: 0 },
  { cohortMonth: '2026-08', initialCustomers: 980, m0: 100, m1: 0, m2: 0, m3: 0, m4: 0, m5: 0 }
];

export const RFM_SEGMENTS: RFMSegmentData[] = [
  {
    segment: 'Champions (VIP Tier)',
    description: 'Bought recently, buy often, and spend the most. Brand ambassadors.',
    customerCount: 385,
    percentOfTotal: 11.2,
    avgLTV: 285.50,
    totalRevenue: 109917.50,
    recommendedAction: 'Exclusive early access, private VIP WhatsApp concierge, zero shipping threshold.',
    color: '#8b5cf6'
  },
  {
    segment: 'Loyal Customers',
    description: 'Buy on a regular basis, responsive to promotions.',
    customerCount: 640,
    percentOfTotal: 18.6,
    avgLTV: 142.20,
    totalRevenue: 91008.00,
    recommendedAction: 'Upsell higher-value apparel, reward with loyalty store credits.',
    color: '#3b82f6'
  },
  {
    segment: 'Potential Loyalists',
    description: 'Recent customers with average frequency, good potential.',
    customerCount: 820,
    percentOfTotal: 23.8,
    avgLTV: 88.40,
    totalRevenue: 72488.00,
    recommendedAction: 'Offer bundle discounts (e.g. Tee + Cap combo), recommend accessories.',
    color: '#06b6d4'
  },
  {
    segment: 'New Customers',
    description: 'Bought recently for the first time.',
    customerCount: 950,
    percentOfTotal: 27.6,
    avgLTV: 45.00,
    totalRevenue: 42750.00,
    recommendedAction: 'Send onboarding email sequence, introduce brand story and sizing guarantee.',
    color: '#10b981'
  },
  {
    segment: 'At Risk (Need Attention)',
    description: 'Spent good money and bought often, but haven\'t purchased in >60 days.',
    customerCount: 420,
    percentOfTotal: 12.2,
    avgLTV: 112.00,
    totalRevenue: 47040.00,
    recommendedAction: 'Trigger win-back SMS/WhatsApp voucher with RM 15 off next purchase.',
    color: '#f59e0b'
  },
  {
    segment: 'Hibernating / Churned',
    description: 'Last purchase was long ago, low spend.',
    customerCount: 230,
    percentOfTotal: 6.6,
    avgLTV: 38.00,
    totalRevenue: 8740.00,
    recommendedAction: 'Re-engagement survey or high-discount clearance broadcast.',
    color: '#ef4444'
  }
];

export const COURIER_SLA_METRICS: CourierSlaReport[] = [
  {
    courierCode: 'posmalaysia',
    courierName: 'Pos Malaysia (Pos Laju Express)',
    totalParcels: 1420,
    onTimePercent: 98.4,
    avgTransitDays: 1.3,
    firstAttemptSuccessPercent: 96.2,
    avgCostPerParcel: 6.80,
    weightDiscrepancyCount: 3,
    weightDiscrepancyAmountRM: 18.50,
    codCollectionAccuracy: 99.8
  },
  {
    courierCode: 'ninjavan',
    courierName: 'Ninja Van Malaysia',
    totalParcels: 980,
    onTimePercent: 96.8,
    avgTransitDays: 1.5,
    firstAttemptSuccessPercent: 94.8,
    avgCostPerParcel: 7.20,
    weightDiscrepancyCount: 8,
    weightDiscrepancyAmountRM: 48.00,
    codCollectionAccuracy: 99.1
  },
  {
    courierCode: 'jnt',
    courierName: 'J&T Express Malaysia',
    totalParcels: 650,
    onTimePercent: 95.2,
    avgTransitDays: 1.6,
    firstAttemptSuccessPercent: 93.5,
    avgCostPerParcel: 6.50,
    weightDiscrepancyCount: 14,
    weightDiscrepancyAmountRM: 92.40,
    codCollectionAccuracy: 98.4
  },
  {
    courierCode: 'flashexpress',
    courierName: 'Flash Express Malaysia',
    totalParcels: 280,
    onTimePercent: 94.5,
    avgTransitDays: 1.7,
    firstAttemptSuccessPercent: 92.0,
    avgCostPerParcel: 6.20,
    weightDiscrepancyCount: 6,
    weightDiscrepancyAmountRM: 36.00,
    codCollectionAccuracy: 97.8
  },
  {
    courierCode: 'easyparcel',
    courierName: 'EasyParcel Multi-Carrier Engine',
    totalParcels: 124,
    onTimePercent: 97.2,
    avgTransitDays: 2.2,
    firstAttemptSuccessPercent: 95.0,
    avgCostPerParcel: 11.50,
    weightDiscrepancyCount: 1,
    weightDiscrepancyAmountRM: 8.00,
    codCollectionAccuracy: 99.5
  }
];

export const MARKETING_CAMPAIGNS: MarketingCampaignPerformance[] = [
  {
    campaignName: 'TikTok Live August Mega Drop #JWStudio',
    channel: 'TikTok Ads & Creator Affiliate',
    spend: 3200.00,
    impressions: 480000,
    clicks: 22400,
    conversions: 620,
    revenueGenerated: 28520.00,
    roas: 8.91,
    cpa: 5.16
  },
  {
    campaignName: 'Meta IG Reels - Luxury Streetwear Lifestyle',
    channel: 'Instagram & Facebook Ads',
    spend: 2800.00,
    impressions: 340000,
    clicks: 14800,
    conversions: 410,
    revenueGenerated: 21320.00,
    roas: 7.61,
    cpa: 6.83
  },
  {
    campaignName: 'Google Search Intent - Heavyweight Cotton Tee Malaysia',
    channel: 'Google Search / Shopping',
    spend: 1400.00,
    impressions: 85000,
    clicks: 6200,
    conversions: 240,
    revenueGenerated: 12480.00,
    roas: 8.91,
    cpa: 5.83
  },
  {
    campaignName: 'WhatsApp VIP Broadcast (RM15 Off Payday Promo)',
    channel: 'Direct WhatsApp Automation',
    spend: 180.00,
    impressions: 4200,
    clicks: 1840,
    conversions: 195,
    revenueGenerated: 9945.00,
    roas: 55.25,
    cpa: 0.92
  }
];

export const MALAYSIA_TAX_REPORT: TaxReportSummary[] = [
  {
    taxCategory: 'Standard Rate SST (6% Goods & Freight)',
    taxRatePercent: 6.0,
    taxableSalesRM: 118400.00,
    taxCollectedRM: 7104.00,
    exemptSalesRM: 0,
    invoiceCount: 2950
  },
  {
    taxCategory: 'Digital Platform Service Tax (8% DSP)',
    taxRatePercent: 8.0,
    taxableSalesRM: 17850.40,
    taxCollectedRM: 1428.03,
    exemptSalesRM: 0,
    invoiceCount: 532
  },
  {
    taxCategory: 'Tax-Exempt Export / Duty-Free (Labuan/Langkawi)',
    taxRatePercent: 0.0,
    taxableSalesRM: 0.00,
    taxCollectedRM: 0.00,
    exemptSalesRM: 3200.00,
    invoiceCount: 48
  }
];

export const PAYMENT_GATEWAY_FEES_REPORT: PaymentGatewayFeeReport[] = [
  {
    gatewayName: 'FPX & DuitNow QR (ToyyibPay / Curlec)',
    channelCode: 'fpx_duitnow',
    transactionCount: 2150,
    grossVolumeRM: 89400.00,
    feePercentage: 1.0,
    fixedFeeRM: 0.50,
    totalFeesDeductedRM: 1969.00,
    netSettlementRM: 87431.00,
    avgSettlementDays: 'T+1 (Daily Bank-In)'
  },
  {
    gatewayName: 'Visa & Mastercard (Stripe Malaysia)',
    channelCode: 'stripe_cc',
    transactionCount: 820,
    grossVolumeRM: 38200.00,
    feePercentage: 2.8,
    fixedFeeRM: 1.00,
    totalFeesDeductedRM: 1889.60,
    netSettlementRM: 36310.40,
    avgSettlementDays: 'T+2 (Rolling Auto)'
  },
  {
    gatewayName: 'Cash on Delivery Remittance (Pos Laju & Ninja)',
    channelCode: 'courier_cod',
    transactionCount: 460,
    grossVolumeRM: 18820.00,
    feePercentage: 2.0,
    fixedFeeRM: 0.00,
    totalFeesDeductedRM: 376.40,
    netSettlementRM: 18443.60,
    avgSettlementDays: 'Weekly Settlement (Every Friday)'
  },
  {
    gatewayName: 'Touch \'n Go eWallet Direct',
    channelCode: 'tng_ewallet',
    transactionCount: 52,
    grossVolumeRM: 2500.00,
    feePercentage: 1.5,
    fixedFeeRM: 0.00,
    totalFeesDeductedRM: 37.50,
    netSettlementRM: 2462.50,
    avgSettlementDays: 'T+1'
  }
];
