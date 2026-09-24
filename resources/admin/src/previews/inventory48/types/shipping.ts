export type CourierCode = 
  | 'jnt' 
  | 'ninjavan' 
  | 'posmalaysia' 
  | 'flashexpress' 
  | 'dhlecommerce' 
  | 'dhl_express'
  | 'easyparcel' 
  | 'delyva'
  | 'gdex'
  | 'citylink'
  | 'manual'
  | 'other';

export type MalaysiaRegion = 'peninsular' | 'east_malaysia';

export type MalaysiaState = 
  | 'kuala_lumpur'
  | 'selangor'
  | 'penang'
  | 'johor'
  | 'sabah'
  | 'sarawak'
  | 'perak'
  | 'melaka'
  | 'pahang'
  | 'negeri_sembilan'
  | 'kedah'
  | 'kelantan'
  | 'terengganu'
  | 'perlis'
  | 'labuan'
  | 'putrajaya';

export interface CourierServiceOption {
  code: string;
  name: string;
  description: string;
  estDays: string;
  baseRateRM: number;
}

export interface CourierConfig {
  id: string;
  courierCode: CourierCode;
  name: string;
  displayName: string;
  logo: string;
  category: 'direct_courier' | 'aggregator_api';
  isEnabled: boolean;
  environment: 'production' | 'sandbox';
  apiKey: string;
  apiSecret: string;
  accountNo?: string;
  merchantCode?: string;
  senderHubName: string;
  senderHubAddress: string;
  senderHubPostcode: string;
  senderHubState: string;
  senderContactPhone: string;
  walletBalance?: number; // For EasyParcel & Delyva aggregators
  walletMinThreshold?: number;
  autoTopUpEnabled?: boolean;
  supportsCOD: boolean;
  codFeePercent: number;
  supportsEastMalaysia: boolean;
  supportsAirFreightHazmat: boolean;
  supportedServices: CourierServiceOption[];
  lastPingLatency?: number; // in ms
  status: 'connected' | 'not_connected' | 'error' | 'pending_keys';
  webhookEndpointUrl: string;
  autoRetryQueue: boolean;
  maxRetryAttempts: number;
  easyparcelOAuth?: {
    isConnected: boolean;
    accountType?: 'demo' | 'live';
    accountDisplayName?: string;
    accountEmailMasked?: string;
    connectedAt?: string;
  };
}

export interface EasyParcelRateOption {
  serviceId: string;
  serviceName: string;
  courierName: string;
  courierCode: string;
  courierLogo: string;
  priceRM: number;
  estDays: string;
  pickupType: 'Doorstep Pickup' | 'Drop-off Point';
  supportsCOD: boolean;
  highlights?: string;
}

export interface SmartRoutingRule {
  id: string;
  name: string;
  priority: number;
  isActive: boolean;
  conditions: {
    destinationRegion?: 'all' | 'peninsular' | 'east_malaysia';
    destinationStates?: string[];
    minWeightKg?: number;
    maxWeightKg?: number;
    isCOD?: boolean;
    isHazardousAirProhibited?: boolean;
    minOrderValue?: number;
    maxOrderValue?: number;
  };
  action: {
    preferredCourier: CourierCode;
    fallbackCourier: CourierCode;
    serviceCode: string;
    autoRequireSignature: boolean;
    customNote?: string;
  };
}

export type ShipmentStatus = 
  | 'pending'
  | 'ready_to_ship'
  | 'picked_up'
  | 'in_transit'
  | 'out_for_delivery'
  | 'delivered'
  | 'failed_delivery'
  | 'returned'
  | 'cancelled';

export type FulfillmentMethodType = 'easyparcel' | 'external_courier' | 'manual_delivery';
export type ManagementModeType = 'provider_managed' | 'team_managed';

export interface ShipmentTrackingEvent {
  timestamp: string;
  location: string;
  status: string;
  description: string;
  rawEventCode?: string;
}

export interface ShipmentParcel {
  id: string;
  orderId: string;
  orderNumber: string;
  customerName: string;
  customerPhone: string;
  customerEmail: string;
  recipientAddress: {
    street: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
    region: MalaysiaRegion;
    lat?: number;
    lng?: number;
  };
  items: {
    sku: string;
    name: string;
    qty: number;
    unitPrice: number;
    weightKg: number;
    isHazardousLiquidBattery?: boolean;
  }[];
  totalWeightKg: number;
  volumetricWeightKg: number; // (L x W x H) / 5000
  dimensionsCm: {
    length: number;
    width: number;
    height: number;
  };
  courier: CourierCode;
  serviceType: string;
  trackingNumber: string;
  routingSortCode: string; // e.g. KUL-HUB-04, BKI-A1
  status: ShipmentStatus;
  paymentMode: 'prepaid' | 'cod';
  codAmount?: number;
  isEastMalaysiaAirRestricted: boolean;
  postcodeValidationStatus: 'valid' | 'warning_mismatch' | 'invalid';
  createdAt: string;
  awbGeneratedAt?: string;
  dispatchedAt?: string;
  estimatedDeliveryDate?: string;
  deliveredAt?: string;
  actualCourierCharge?: number;
  systemQuotedCharge: number;
  isWeightDiscrepancy: boolean;
  exceptionReason?: string; // e.g. "NITC - Recipient unreachable", "Incorrect Postcode", "Address not found"
  exceptionAttempts?: number;
  pickupRequestId?: string;
  trackingEvents: ShipmentTrackingEvent[];
  fulfillmentMethod?: FulfillmentMethodType;
  managementMode?: ManagementModeType;
  externalCourierName?: string;
  trackingUrl?: string;
  driverName?: string;
  handoffReference?: string;
  dispatchAuditNote?: string;
  isAwaitingReplacement?: boolean;
  voidReason?: string;
  voidedAt?: string;
  voidedBy?: string;
  correctionHistory?: ShipmentCorrectionRecord[];
  previousShipmentSnapshots?: {
    id: string;
    voidedAt: string;
    fulfillmentMethod?: FulfillmentMethodType;
    managementMode?: ManagementModeType;
    courier: CourierCode;
    externalCourierName?: string;
    trackingNumber: string;
    handoffReference?: string;
    driverName?: string;
    status: ShipmentStatus;
    reason: string;
    auditNote: string;
  }[];
}

export type CorrectionReasonType =
  | 'wrong_fulfillment_method'
  | 'wrong_easyparcel_courier'
  | 'wrong_external_courier'
  | 'incorrect_package_info'
  | 'incorrect_receiver_details'
  | 'duplicate_accidental_dispatch'
  | 'customer_requested_change'
  | 'other_operational_error';

export interface ShipmentCorrectionRecord {
  id: string;
  timestamp: string;
  performedBy: string;
  reason: CorrectionReasonType | string;
  reasonLabel: string;
  auditNote: string;
  actionType: 'void_and_replace' | 'correct_details_only';
  previousMethod?: FulfillmentMethodType;
  previousCourier?: CourierCode;
  previousTrackingNumber?: string;
  previousHandoffReference?: string;
  previousDriverName?: string;
  providerCancellationStatus?: 'confirmed' | 'pending_acknowledgement' | 'not_applicable';
  impactSummary: {
    orderStatusKeptActive: boolean;
    inventoryUnchanged: boolean;
    paymentUnchanged: boolean;
    orderCancellationEmailSuppressed: boolean;
  };
}

export interface AddressAmendmentRecord {
  id: string;
  orderNumber: string;
  timestamp: string;
  performedBy: string;
  amendmentReason: string;
  internalNote: string;
  savedToCustomerAddressBook: boolean;
  before: {
    firstName: string;
    lastName: string;
    phone: string;
    address1: string;
    address2?: string;
    city: string;
    province: string;
    postalCode: string;
    country: string;
  };
  after: {
    firstName: string;
    lastName: string;
    phone: string;
    address1: string;
    address2?: string;
    city: string;
    province: string;
    postalCode: string;
    country: string;
  };
}

export interface PickupRequest {
  id: string;
  pickupRefNo: string;
  courier: CourierCode;
  pickupDate: string;
  timeSlot: string; // '10:00 - 13:00' | '14:00 - 18:00'
  totalParcels: number;
  totalWeightKg: number;
  vehicleType: 'motorcycle' | 'car' | 'van_1ton' | 'lorry_3ton';
  pickupAddress: string;
  contactPerson: string;
  contactPhone: string;
  driverNotes?: string;
  status: 'confirmed' | 'pending' | 'completed' | 'cancelled';
  assignedDriver?: {
    name: string;
    phone: string;
    vehiclePlate: string;
  };
  createdAt: string;
}

export interface BillingReconciliationRecord {
  id: string;
  invoiceNumber: string;
  billingMonth: string;
  courier: CourierCode;
  trackingNumber: string;
  orderNumber: string;
  systemWeightKg: number;
  courierBilledWeightKg: number;
  systemRateRM: number;
  courierBilledRateRM: number;
  sstTaxRM: number;
  varianceRM: number;
  discrepancyType: 'normal' | 'volumetric_surge' | 'surcharge_mismatch' | 'unregistered_shipment';
  reconciliationStatus: 'matched' | 'disputed' | 'resolved';
}

export interface PostcodeMapping {
  postcode: string;
  city: string;
  state: string;
  region: MalaysiaRegion;
  zoneCode: string;
  isRuralArea: boolean;
}

export type PostageCalculationMode = 'weight' | 'quantity' | 'per_order';

export type RateType = 'custom' | 'tiered' | 'flat';

export interface ShippingRateTier {
  id: string;
  min: number; // e.g. > 0
  max: number; // e.g. to 2
  cost: number; // e.g. = RM 8
}

export interface GeneralShippingRate {
  id: string;
  name: string;
  zoneRegion: 'all' | 'peninsular' | 'east_malaysia' | 'sabah' | 'sarawak' | 'international' | 'custom';
  customStates?: string[];
  logisticType: 'all' | CourierCode;
  calculationMode: PostageCalculationMode; // 'weight' | 'quantity' | 'per_order'
  rateType: RateType; // 'custom' | 'tiered' | 'flat'
  tiers: ShippingRateTier[];
  subsequentRate?: number; // e.g. every 1 kg or 1 piece
  subsequentCost?: number; // e.g. RM 2.00
  flatRateCost?: number; // e.g. RM 10.00 for per_order
  freeShippingThreshold?: number; // e.g. Free shipping above RM 150
  estimatedDays?: string; // e.g. 1-3 Business Days
  isActive: boolean;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}
