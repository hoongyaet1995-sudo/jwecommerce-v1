export type CourierCode = 
  | 'jnt' 
  | 'ninjavan' 
  | 'posmalaysia' 
  | 'flashexpress' 
  | 'dhlecommerce' 
  | 'easyparcel' 
  | 'delyva'
  | 'dhl'
  | 'dhl_express'
  | 'gdex'
  | 'citylink'
  | 'other'
  | 'manual'
  | 'unassigned';

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

export type MalaysiaRegion = 'peninsular' | 'east_malaysia' | 'sabah' | 'sarawak';

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
  status: 'connected' | 'error' | 'pending_keys';
  webhookEndpointUrl: string;
  autoRetryQueue: boolean;
  maxRetryAttempts: number;
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
  | 'shipped'
  | 'pending'
  | 'ready_to_ship'
  | 'on_hold'
  | 'cancelled'
  | 'booking_failed'
  | 'provider_update'
  | 'picked_up'
  | 'in_transit'
  | 'out_for_delivery'
  | 'delivered'
  | 'failed_delivery'
  | 'returned';

export type FulfillmentMethodType = 'easyparcel' | 'external_courier' | 'manual_delivery';
export type ManagementModeType = 'provider_managed' | 'team_managed';

export interface ShipmentTrackingEvent {
  timestamp: string;
  location: string;
  status: string;
  description: string;
  rawEventCode?: string;
}

export interface EasyParcelRecoverySnapshot {
  recoveryId: string;
  state: string;
  cancellationState: 'success' | 'error' | 'claimed';
  cancellationMessage: string | null;
  cancellationConfirmed: boolean;
  replacementOrderId: string;
  readyToRebook: boolean;
}

export interface ShipmentCorrectionSnapshot {
  id: string;
  sourceShipmentId: string;
  replacementShipmentId: string | null;
  reasonCode: string;
  externalActionState: 'provider_confirmed' | 'staff_confirmed_external' | 'not_required';
  status: 'awaiting_replacement' | 'completed' | 'closed_without_replacement';
  readyToResume: boolean;
}

export interface StartShipmentCorrectionInput {
  reasonCode: 'wrong_fulfilment_method' | 'incorrect_delivery_details' | 'duplicate_booking' | 'customer_request' | 'sender_information_error' | 'other';
  internalNote: string;
  externalActionState: 'provider_confirmed' | 'staff_confirmed_external' | 'not_required';
  externalActionNote?: string;
}

export interface StartShipmentCorrectionResult {
  id: string;
  orderId: string;
  sourceShipmentId: string;
  status: 'awaiting_replacement';
  externalActionState: StartShipmentCorrectionInput['externalActionState'];
}

export interface AmendShipmentOperationalDetailsInput {
  courierCode?: string | null; courierName?: string | null; serviceName?: string | null;
  trackingNumber?: string | null; trackingUrl?: string | null; driverName?: string | null;
  handoffReference?: string | null;
  reasonCode: 'tracking_typo' | 'courier_correction' | 'driver_correction' | 'handoff_correction' | 'other';
  internalNote: string;
}

export interface ShipmentParcel {
  id: string;
  source?: 'manual' | 'easyparcel' | 'pending';
  fulfillmentMethod?: FulfillmentMethodType | null;
  managementMode?: ManagementModeType | null;
  externalCourierName?: string | null;
  trackingUrl?: string | null;
  driverName?: string | null;
  handoffReference?: string | null;
  dispatchAuditNote?: string | null;
  orderId: string;
  orderNumber: string;
  customerName: string;
  destinationPostcode?: string;
  items: {
    sku: string;
    name: string;
    qty: number;
    unitPrice: number;
    weightKg: number | null;
    isHazardousLiquidBattery?: boolean;
  }[];
  totalWeightKg: number | null;
  volumetricWeightKg: number | null; // (L x W x H) / 5000
  dimensionsCm: {
    length: number;
    width: number;
    height: number;
  } | null;
  courier: CourierCode;
  serviceType: string;
  trackingNumber: string;
  hasLabel: boolean;
  routingSortCode: string; // e.g. KUL-HUB-04, BKI-A1
  status: ShipmentStatus;
  paymentMode: 'prepaid' | 'cod';
  codAmount?: number;
  isEastMalaysiaAirRestricted: boolean;
  postcodeValidationStatus: 'valid' | 'warning_mismatch' | 'invalid' | 'unknown';
  createdAt: string;
  awbGeneratedAt?: string;
  dispatchedAt?: string;
  estimatedDeliveryDate?: string;
  deliveredAt?: string;
  actualCourierCharge?: number;
  systemQuotedCharge: number | null;
  isWeightDiscrepancy: boolean;
  exceptionReason?: string; // e.g. "NITC - Recipient unreachable", "Incorrect Postcode", "Address not found"
  exceptionAttempts?: number;
  easyParcelRecovery?: EasyParcelRecoverySnapshot | null;
  activeCorrection?: ShipmentCorrectionSnapshot | null;
  pickupRequestId?: string;
  trackingEvents: ShipmentTrackingEvent[];
}

export interface EasyParcelWebhookReceiptRow {
  id: string;
  topic: string;
  state: 'received' | 'queued' | 'processed' | 'retrying' | 'failed' | 'ignored' | 'unmatched';
  shipmentId: string | null;
  orderNumber: string | null;
  trackingNumber: string | null;
  receivedAt: string;
  processedAt: string | null;
  attemptCount: number;
  errorCode: string | null;
}

export type EasyParcelWebhookAuditStatus = 'loading' | 'ready' | 'error';

export interface EasyParcelWebhookAuditSnapshot {
  receipts: EasyParcelWebhookReceiptRow[];
  status: EasyParcelWebhookAuditStatus;
  error: string | null;
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
