export type ReportDateRange = 'today' | 'yesterday' | '7d' | '30d' | '90d' | 'this_month' | 'last_month' | 'ytd' | 'custom';

export type ReportChannelFilter = 'all' | 'web_direct' | 'tiktok_shop' | 'shopee' | 'lazada' | 'whatsapp_b2b';

export interface MetricCardData {
  id: string;
  label: string;
  value: string;
  numericValue: number;
  changePercent: number;
  trend: 'up' | 'down' | 'neutral';
  comparisonPeriod: string;
  subtext?: string;
  sparklineData?: number[];
  badge?: string;
}

export interface DailySalesDataPoint {
  date: string;
  dayName: string;
  gmv: number;
  netSales: number;
  ordersCount: number;
  aov: number;
  cogs: number;
  grossProfit: number;
  webSales: number;
  tiktokSales: number;
  shopeeSales: number;
  lazadaSales: number;
}

export interface ChannelPerformance {
  channel: string;
  channelKey: ReportChannelFilter;
  orders: number;
  gmv: number;
  netRevenue: number;
  aov: number;
  conversionRate: number;
  sharePercent: number;
  growthWoW: number;
  color: string;
}

export interface RegionalSalesBreakdown {
  region: string;
  state: string;
  orders: number;
  gmv: number;
  sharePercent: number;
  topCourier: string;
  avgDeliveryDays: number;
}

export interface OrderFunnelStage {
  stage: string;
  count: number;
  percentage: number;
  avgTimeSpentHours: number;
  dropOffRate?: number;
}

export interface ProductPerformanceReport {
  sku: string;
  name: string;
  category: string;
  unitsSold: number;
  revenue: number;
  cogs: number;
  grossMarginPercent: number;
  currentStock: number;
  daysOfInventory: number;
  stockStatus: 'in_stock' | 'low_stock' | 'out_of_stock' | 'overstocked';
  returnRate: number;
  velocityScore: 'Fast' | 'Normal' | 'Slow' | 'Dead';
}

export interface CustomerCohortData {
  cohortMonth: string;
  initialCustomers: number;
  m0: number;
  m1: number;
  m2: number;
  m3: number;
  m4: number;
  m5: number;
}

export interface RFMSegmentData {
  segment: string;
  description: string;
  customerCount: number;
  percentOfTotal: number;
  avgLTV: number;
  totalRevenue: number;
  recommendedAction: string;
  color: string;
}

export interface CourierSlaReport {
  courierCode: string;
  courierName: string;
  totalParcels: number;
  onTimePercent: number;
  avgTransitDays: number;
  firstAttemptSuccessPercent: number;
  avgCostPerParcel: number;
  weightDiscrepancyCount: number;
  weightDiscrepancyAmountRM: number;
  codCollectionAccuracy: number;
}

export interface MarketingCampaignPerformance {
  campaignName: string;
  channel: string;
  spend: number;
  impressions: number;
  clicks: number;
  conversions: number;
  revenueGenerated: number;
  roas: number;
  cpa: number;
}

export interface TaxReportSummary {
  taxCategory: string;
  taxRatePercent: number;
  taxableSalesRM: number;
  taxCollectedRM: number;
  exemptSalesRM: number;
  invoiceCount: number;
}

export interface PaymentGatewayFeeReport {
  gatewayName: string;
  channelCode: string;
  transactionCount: number;
  grossVolumeRM: number;
  feePercentage: number;
  fixedFeeRM: number;
  totalFeesDeductedRM: number;
  netSettlementRM: number;
  avgSettlementDays: string;
}
