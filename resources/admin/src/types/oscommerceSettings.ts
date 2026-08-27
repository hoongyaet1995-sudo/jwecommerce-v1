// osCommerce Architecture Settings Types

export interface CustomOrderStatus {
  id: string;
  code: string;
  name: string;
  description: string;
  color: 'amber' | 'blue' | 'indigo' | 'emerald' | 'rose' | 'purple' | 'cyan' | 'neutral';
  isDefault: boolean; // Default for new incoming orders
  isDefaultPaid?: boolean; // Default status when payment gateway succeeds
  isDefaultCancelled?: boolean; // Default status when refunded/cancelled
  notifyCustomer: boolean; // Auto-select notify customer checkbox in admin order update
  publicVisibility: boolean; // Displayed to customer on tracking portal
  inventoryAction: 'none' | 'reserve' | 'deduct' | 'restock';
  emailTemplate: string; // Bound email template (e.g. "Order Confirmation", "Shipment In-Transit")
  whatsappTemplate?: string;
  sortOrder: number;
  ordersCount?: number;
}

export interface CountrySetting {
  id: string;
  name: string;
  isoCode2: string;
  isoCode3: string;
  dialCode: string;
  currencyCode: string;
  addressFormat: string; // e.g. "{street}\n{postcode} {city}\n{zone}, {country}"
  postcodeRequired: boolean;
  isEnabled: boolean;
  isPopular?: boolean;
  totalZones: number;
  flagEmoji: string;
}

export interface ZoneSetting {
  id: string;
  countryId: string;
  countryName: string;
  zoneName: string; // e.g. "Selangor", "WP Kuala Lumpur"
  zoneCode: string; // e.g. "SGR", "KUL"
  regionType: 'Peninsular Malaysia' | 'East Malaysia (Borneo)' | 'Federal Territory' | 'Singapore District' | 'International State';
  shippingTier: 'Standard Domestic' | 'Air Freight East MY' | 'Express Metro' | 'Duty Free Port';
  isEnabled: boolean;
  postalPrefixes: string[]; // e.g. ["40", "41", "42", "43", "46", "47", "48"]
}

export interface CurrencySetting {
  id: string;
  code: string; // e.g. "MYR", "SGD", "USD"
  title: string; // "Malaysian Ringgit"
  symbolLeft: string; // "RM "
  symbolRight: string;
  decimalPlaces: number;
  exchangeRate: number; // 1.00000000 for base currency
  isDefault: boolean;
  autoUpdate: boolean;
  lastUpdated: string;
  isEnabled: boolean;
}

export type OrderTotalModuleCode = 
  | 'ot_subtotal'
  | 'ot_discount'
  | 'ot_shipping'
  | 'ot_tax'
  | 'ot_reward_points'
  | 'ot_cod_fee'
  | 'ot_rounding'
  | 'ot_total';

export interface OrderTotalModule {
  id: string;
  code: OrderTotalModuleCode;
  title: string; // Configurable display title
  description: string;
  sortOrder: number; // osCommerce calculation sequence
  isEnabled: boolean;
  calculationType: 'additive' | 'deductive' | 'tax_computed' | 'rounding_rule' | 'final_sum';
  config: {
    freeShippingThreshold?: number;
    allowDiscountsOnSaleItems?: boolean;
    applyTaxToShipping?: boolean;
    roundingDirection?: 'nearest_5_cents' | 'nearest_10_cents' | 'round_up' | 'round_down';
    rewardRedemptionRate?: number; // 100 points = RM 1.00
    codHandlingFee?: number; // RM 5.00
  };
}

export interface GeoTaxZone {
  id: string;
  name: string; // e.g. "Malaysia Standard SST Zone", "East Malaysia Exempt", "Export Zero-Rated"
  description: string;
  countryIds: string[];
  zoneIds: string[]; // empty means all zones in those countries
  isActive: boolean;
}

export interface TaxClass {
  id: string;
  name: string;
  ratePercent?: number;
  isActive?: boolean;
  createdAt?: string;
  updatedAt?: string;
  description?: string;
  defaultRate?: number;
  productCount?: number;
}

export interface TaxRate {
  id: string;
  taxClassId: string;
  taxClassName: string;
  geoZoneId: string;
  geoZoneName: string;
  ratePercent: number; // e.g. 8.0, 6.0, 0.0
  description: string; // Displayed on invoice e.g. "Sales & Service Tax (SST 8%)"
  priority: number; // For compound tax calculation
  compound: boolean;
  isEnabled: boolean;
}

export interface GlobalTaxEngineSettings {
  pricesIncludeTax: boolean; // Net vs Gross entry
  calculateTaxBasedOn: 'shipping_address' | 'billing_address' | 'store_origin';
  shippingTaxClassId: string; // Tax applied to shipping cost
  displayTaxInCartSummary: 'single_line' | 'itemized_breakdown';
  taxExemptThreshold?: number;
}
