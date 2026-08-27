import {
  CourierConfig,
  SmartRoutingRule,
  ShipmentParcel,
  PickupRequest,
  BillingReconciliationRecord,
  PostcodeMapping,
  GeneralShippingRate
} from '../types/shipping';

export const INITIAL_COURIER_CONFIGS: CourierConfig[] = [
  {
    id: 'cfg_jnt',
    courierCode: 'jnt',
    name: 'J&T Express Malaysia',
    displayName: 'J&T Express MY',
    logo: 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=100&auto=format&fit=crop&q=60',
    category: 'direct_courier',
    isEnabled: true,
    environment: 'production',
    apiKey: 'jnt_live_key_998418029318',
    apiSecret: 'jnt_sec_d8374910283719481928374615',
    accountNo: 'JNT-MY-88291039',
    merchantCode: 'JW_STUDIO_VIP_01',
    senderHubName: 'JW Studio Central Warehouse Subang',
    senderHubAddress: 'No. 18, Jalan TP 5, Taman Perindustrian UEP, 47600 Subang Jaya',
    senderHubPostcode: '47600',
    senderHubState: 'Selangor',
    senderContactPhone: '+0142244200',
    supportsCOD: true,
    codFeePercent: 2.0,
    supportsEastMalaysia: true,
    supportsAirFreightHazmat: false,
    supportedServices: [
      { code: 'EZ', name: 'Standard Express', description: 'Next-day delivery within Peninsular Malaysia', estDays: '1-2 Days', baseRateRM: 6.50 },
      { code: 'DOC', name: 'Document Express', description: 'Priority envelope & documents', estDays: '1 Day', baseRateRM: 5.00 },
      { code: 'EM_EXP', name: 'East Malaysia Air Express', description: 'Air cargo to Sabah & Sarawak', estDays: '2-4 Days', baseRateRM: 14.50 }
    ],
    lastPingLatency: 42,
    status: 'connected',
    webhookEndpointUrl: 'https://api.jwstudio.online/v1/webhooks/courier/jnt',
    autoRetryQueue: true,
    maxRetryAttempts: 3
  },
  {
    id: 'cfg_ninjavan',
    courierCode: 'ninjavan',
    name: 'Ninja Van Malaysia',
    displayName: 'Ninja Van MY',
    logo: 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=100&auto=format&fit=crop&q=60',
    category: 'direct_courier',
    isEnabled: true,
    environment: 'production',
    apiKey: 'nv_client_id_819203810293847',
    apiSecret: 'nv_secret_99182374619283746192837461',
    accountNo: 'NV-MY-00918273',
    senderHubName: 'JW Studio Hub Shah Alam Seksyen 22',
    senderHubAddress: 'Lot 4, Persiaran Jubli Perak, Seksyen 22, 40300 Shah Alam',
    senderHubPostcode: '40300',
    senderHubState: 'Selangor',
    senderContactPhone: '+0142244200',
    supportsCOD: true,
    codFeePercent: 2.5,
    supportsEastMalaysia: true,
    supportsAirFreightHazmat: false,
    supportedServices: [
      { code: 'NINJA_STD', name: 'Ninja Standard', description: 'Reliable parcel delivery with 3 delivery attempts', estDays: '1-3 Days', baseRateRM: 6.80 },
      { code: 'NINJA_NEXTDAY', name: 'Ninja Next Day Priority', description: 'Guaranteed 24-hr delivery in Klang Valley', estDays: '1 Day', baseRateRM: 9.50 },
      { code: 'NINJA_RETURN', name: 'Ninja Return (RMA Drop-off)', description: 'Prepaid reverse logistics for customer returns', estDays: '2-3 Days', baseRateRM: 6.50 }
    ],
    lastPingLatency: 68,
    status: 'connected',
    webhookEndpointUrl: 'https://api.jwstudio.online/v1/webhooks/courier/ninjavan',
    autoRetryQueue: true,
    maxRetryAttempts: 3
  },
  {
    id: 'cfg_posmalaysia',
    courierCode: 'posmalaysia',
    name: 'Pos Malaysia / Pos Laju',
    displayName: 'Pos Laju (National Post)',
    logo: 'https://images.unsplash.com/photo-1526367790999-0150786686a2?w=100&auto=format&fit=crop&q=60',
    category: 'direct_courier',
    isEnabled: true,
    environment: 'production',
    apiKey: 'pos_api_live_my_772819039182',
    apiSecret: 'pos_sec_key_448192038471928374',
    accountNo: 'POS-CORP-8839102',
    senderHubName: 'JW Studio Warehouse KL Hub',
    senderHubAddress: 'Bangsar South, 59200 Kuala Lumpur',
    senderHubPostcode: '59200',
    senderHubState: 'Kuala Lumpur',
    senderContactPhone: '+0142244200',
    supportsCOD: false,
    codFeePercent: 0,
    supportsEastMalaysia: true,
    supportsAirFreightHazmat: true,
    supportedServices: [
      { code: 'POS_DOMESTIC', name: 'Pos Laju Domestic', description: 'Unmatched 100% postcode coverage across West & East Malaysia', estDays: '2-4 Days', baseRateRM: 7.20 },
      { code: 'POS_EM_SURFACE', name: 'East Malaysia Surface Sea Freight', description: 'Economical sea shipping for heavy cargo/batteries to Sabah/Sarawak', estDays: '7-14 Days', baseRateRM: 8.50 },
      { code: 'POS_INTERNATIONAL', name: 'Pos Laju EMS Cross-border', description: 'Worldwide EMS with customs declaration (Singapore/Brunei/UK)', estDays: '3-6 Days', baseRateRM: 35.00 }
    ],
    lastPingLatency: 110,
    status: 'connected',
    webhookEndpointUrl: 'https://api.jwstudio.online/v1/webhooks/courier/posmalaysia',
    autoRetryQueue: false,
    maxRetryAttempts: 2
  },
  {
    id: 'cfg_easyparcel',
    courierCode: 'easyparcel',
    name: 'EasyParcel Malaysia (Aggregator)',
    displayName: 'EasyParcel Multi-Carrier',
    logo: 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=100&auto=format&fit=crop&q=60',
    category: 'aggregator_api',
    isEnabled: true,
    environment: 'production',
    apiKey: 'ep_live_sec_key_88392019284710',
    apiSecret: 'ep_webhook_sec_1092837461928374',
    accountNo: 'EP-MY-WALLET-9921',
    senderHubName: 'JW Studio Subang Fulfillment Center',
    senderHubAddress: 'No. 18, Jalan TP 5, Taman Perindustrian UEP, 47600 Subang Jaya',
    senderHubPostcode: '47600',
    senderHubState: 'Selangor',
    senderContactPhone: '+0142244200',
    walletBalance: 1485.60,
    walletMinThreshold: 200.00,
    autoTopUpEnabled: true,
    supportsCOD: true,
    codFeePercent: 2.0,
    supportsEastMalaysia: true,
    supportsAirFreightHazmat: true,
    supportedServices: [
      { code: 'EP_BEST_RATE', name: 'EasyParcel Smart Lowest Rate', description: 'Auto-selects lowest rate among Pgeon, J&T, Teleport, ABX', estDays: '1-3 Days', baseRateRM: 5.90 },
      { code: 'EP_PGEON_DROP', name: 'Pgeon Point Drop-off', description: 'Drop off at 99 Speedmart or convenience stores', estDays: '2-3 Days', baseRateRM: 5.50 }
    ],
    lastPingLatency: 55,
    status: 'connected',
    webhookEndpointUrl: 'https://api.jwstudio.online/v1/webhooks/courier/easyparcel',
    autoRetryQueue: true,
    maxRetryAttempts: 3
  },
  {
    id: 'cfg_flashexpress',
    courierCode: 'flashexpress',
    name: 'Flash Express Malaysia',
    displayName: 'Flash Express MY',
    logo: 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=100&auto=format&fit=crop&q=60',
    category: 'direct_courier',
    isEnabled: true,
    environment: 'production',
    apiKey: 'flash_mch_88192038471029',
    apiSecret: 'flash_sec_key_77192837461928',
    accountNo: 'FE-MY-001928',
    senderHubName: 'JW Studio Warehouse Petaling Jaya',
    senderHubAddress: 'Section 51A, 46100 Petaling Jaya, Selangor',
    senderHubPostcode: '46100',
    senderHubState: 'Selangor',
    senderContactPhone: '+0142244200',
    supportsCOD: true,
    codFeePercent: 1.8,
    supportsEastMalaysia: false,
    supportsAirFreightHazmat: false,
    supportedServices: [
      { code: 'FLASH_STD', name: 'Flash Standard Door-to-Door', description: '365-day doorstep pickup & delivery in Peninsular MY', estDays: '1-2 Days', baseRateRM: 5.80 }
    ],
    lastPingLatency: 48,
    status: 'connected',
    webhookEndpointUrl: 'https://api.jwstudio.online/v1/webhooks/courier/flashexpress',
    autoRetryQueue: true,
    maxRetryAttempts: 3
  },
  {
    id: 'cfg_dhlecommerce',
    courierCode: 'dhlecommerce',
    name: 'DHL eCommerce Malaysia',
    displayName: 'DHL eCommerce MY',
    logo: 'https://images.unsplash.com/photo-1616401784845-180882ba9ba8?w=100&auto=format&fit=crop&q=60',
    category: 'direct_courier',
    isEnabled: true,
    environment: 'production',
    apiKey: 'dhl_ecom_live_my_55819203847',
    apiSecret: 'dhl_ecom_sec_key_99812736451928',
    accountNo: 'DHL-MY-66291039',
    senderHubName: 'JW Studio Warehouse KL Hub',
    senderHubAddress: 'Bangsar South, 59200 Kuala Lumpur',
    senderHubPostcode: '59200',
    senderHubState: 'Kuala Lumpur',
    senderContactPhone: '+0142244200',
    supportsCOD: true,
    codFeePercent: 2.5,
    supportsEastMalaysia: true,
    supportsAirFreightHazmat: true,
    supportedServices: [
      { code: 'DHL_DOM_PARCEL', name: 'DHL Domestic Parcel Plus', description: 'Premium B2C parcel with full real-time milestone GPS tracking', estDays: '1-2 Days', baseRateRM: 8.50 },
      { code: 'DHL_CROSSBORDER_SG', name: 'DHL Cross-border to Singapore', description: 'Direct overland express to Singapore (Door-to-door)', estDays: '2-3 Days', baseRateRM: 18.00 }
    ],
    lastPingLatency: 75,
    status: 'connected',
    webhookEndpointUrl: 'https://api.jwstudio.online/v1/webhooks/courier/dhlecommerce',
    autoRetryQueue: true,
    maxRetryAttempts: 3
  },
  {
    id: 'cfg_delyva',
    courierCode: 'delyva',
    name: 'DelyvaNow (On-Demand & Same Day)',
    displayName: 'Delyva Instant & Express',
    logo: 'https://images.unsplash.com/photo-1580674684081-7617fbf3d745?w=100&auto=format&fit=crop&q=60',
    category: 'aggregator_api',
    isEnabled: false,
    environment: 'sandbox',
    apiKey: 'delyva_test_key_33819203847',
    apiSecret: 'delyva_test_sec_99182374615',
    accountNo: 'DELYVA-MY-TEST',
    senderHubName: 'JW Studio Central Warehouse Subang',
    senderHubAddress: 'No. 18, Jalan TP 5, Taman Perindustrian UEP, 47600 Subang Jaya',
    senderHubPostcode: '47600',
    senderHubState: 'Selangor',
    senderContactPhone: '+0142244200',
    walletBalance: 250.00,
    walletMinThreshold: 100.00,
    autoTopUpEnabled: false,
    supportsCOD: false,
    codFeePercent: 0,
    supportsEastMalaysia: false,
    supportsAirFreightHazmat: false,
    supportedServices: [
      { code: 'DELYVA_INSTANT', name: 'Delyva Instant Motor/Car (Grab/Lalamove/Borzo)', description: 'Same-day 1-4 hour dispatch within Klang Valley', estDays: '1-4 Hours', baseRateRM: 12.00 }
    ],
    lastPingLatency: 92,
    status: 'pending_keys',
    webhookEndpointUrl: 'https://api.aurora-store.my/v1/webhooks/courier/delyva',
    autoRetryQueue: false,
    maxRetryAttempts: 2
  }
];

export const INITIAL_SMART_ROUTING_RULES: SmartRoutingRule[] = [
  {
    id: 'rule_em_pos',
    name: 'East Malaysia (Sabah & Sarawak) Routing Rule',
    priority: 1,
    isActive: true,
    conditions: {
      destinationRegion: 'east_malaysia',
      destinationStates: ['Sabah', 'Sarawak', 'Labuan'],
      isHazardousAirProhibited: false
    },
    action: {
      preferredCourier: 'posmalaysia',
      fallbackCourier: 'easyparcel',
      serviceCode: 'POS_DOMESTIC',
      autoRequireSignature: true,
      customNote: 'CN22 Customs Declaration required for East Malaysia shipment.'
    }
  },
  {
    id: 'rule_cod_orders',
    name: 'Cash On Delivery (COD) Priority Routing',
    priority: 2,
    isActive: true,
    conditions: {
      isCOD: true,
      destinationRegion: 'peninsular'
    },
    action: {
      preferredCourier: 'ninjavan',
      fallbackCourier: 'jnt',
      serviceCode: 'NINJA_STD',
      autoRequireSignature: true,
      customNote: 'Collect full cash payment upon parcel delivery before handover.'
    }
  },
  {
    id: 'rule_wm_lightweight',
    name: 'Peninsular Malaysia Lightweight (< 3kg) Cost Optimizer',
    priority: 3,
    isActive: true,
    conditions: {
      destinationRegion: 'peninsular',
      maxWeightKg: 3.0,
      isCOD: false
    },
    action: {
      preferredCourier: 'flashexpress',
      fallbackCourier: 'jnt',
      serviceCode: 'FLASH_STD',
      autoRequireSignature: false,
      customNote: 'Standard door-to-door e-commerce delivery.'
    }
  },
  {
    id: 'rule_air_hazmat',
    name: 'Battery / Liquid Sea-Freight Safety Fallback',
    priority: 4,
    isActive: true,
    conditions: {
      destinationRegion: 'east_malaysia',
      isHazardousAirProhibited: true
    },
    action: {
      preferredCourier: 'posmalaysia',
      fallbackCourier: 'easyparcel',
      serviceCode: 'POS_EM_SURFACE',
      autoRequireSignature: true,
      customNote: 'Non-flammable liquid/battery declaration. Sea freight transit 7-14 days.'
    }
  }
];

export const INITIAL_MALAYSIA_POSTCODES: PostcodeMapping[] = [
  { postcode: '50480', city: 'Kuala Lumpur', state: 'Kuala Lumpur', region: 'peninsular', zoneCode: 'KUL-01', isRuralArea: false },
  { postcode: '59200', city: 'Bangsar', state: 'Kuala Lumpur', region: 'peninsular', zoneCode: 'KUL-02', isRuralArea: false },
  { postcode: '47600', city: 'Subang Jaya', state: 'Selangor', region: 'peninsular', zoneCode: 'SEL-04', isRuralArea: false },
  { postcode: '40300', city: 'Shah Alam', state: 'Selangor', region: 'peninsular', zoneCode: 'SEL-01', isRuralArea: false },
  { postcode: '46100', city: 'Petaling Jaya', state: 'Selangor', region: 'peninsular', zoneCode: 'SEL-02', isRuralArea: false },
  { postcode: '10100', city: 'George Town', state: 'Penang', region: 'peninsular', zoneCode: 'PNG-01', isRuralArea: false },
  { postcode: '13700', city: 'Seberang Jaya', state: 'Penang', region: 'peninsular', zoneCode: 'PNG-03', isRuralArea: false },
  { postcode: '80000', city: 'Johor Bahru', state: 'Johor', region: 'peninsular', zoneCode: 'JHR-01', isRuralArea: false },
  { postcode: '88000', city: 'Kota Kinabalu', state: 'Sabah', region: 'east_malaysia', zoneCode: 'BKI-01', isRuralArea: false },
  { postcode: '89000', city: 'Keningau', state: 'Sabah', region: 'east_malaysia', zoneCode: 'BKI-05', isRuralArea: true },
  { postcode: '93050', city: 'Kuching', state: 'Sarawak', region: 'east_malaysia', zoneCode: 'KCH-01', isRuralArea: false },
  { postcode: '98000', city: 'Miri', state: 'Sarawak', region: 'east_malaysia', zoneCode: 'MYY-01', isRuralArea: false },
  { postcode: '30000', city: 'Ipoh', state: 'Perak', region: 'peninsular', zoneCode: 'IPH-01', isRuralArea: false },
  { postcode: '75000', city: 'Melaka City', state: 'Melaka', region: 'peninsular', zoneCode: 'MLK-01', isRuralArea: false },
  { postcode: '25000', city: 'Kuantan', state: 'Pahang', region: 'peninsular', zoneCode: 'KUA-01', isRuralArea: false },
  { postcode: '70000', city: 'Seremban', state: 'Negeri Sembilan', region: 'peninsular', zoneCode: 'SBN-01', isRuralArea: false }
];

export const INITIAL_SHIPMENT_PARCELS: ShipmentParcel[] = [
  {
    id: 'shp_my_101',
    orderId: 'ord_101',
    orderNumber: 'ORD-2026-9041',
    customerName: 'Nurul Ain Binti Yusof',
    items: [
      { sku: 'LUM-SER-01', name: 'Luminance Silk Hydration Serum (50ml)', qty: 2, unitPrice: 89.00, weightKg: 0.25, isHazardousLiquidBattery: false },
      { sku: 'LUM-CRM-02', name: 'Barrier Restoring Peptide Cream (30g)', qty: 1, unitPrice: 120.00, weightKg: 0.15, isHazardousLiquidBattery: false }
    ],
    totalWeightKg: 0.65,
    volumetricWeightKg: 0.48,
    dimensionsCm: { length: 20, width: 15, height: 8 },
    courier: 'jnt',
    serviceType: 'EZ (Standard Express)',
    trackingNumber: 'JNT6019283749MY',
    routingSortCode: 'SEL-HUB-04',
    status: 'in_transit',
    paymentMode: 'prepaid',
    isEastMalaysiaAirRestricted: false,
    postcodeValidationStatus: 'valid',
    createdAt: '2026-08-16T10:30:00Z',
    awbGeneratedAt: '2026-08-16T11:00:00Z',
    dispatchedAt: '2026-08-16T16:45:00Z',
    estimatedDeliveryDate: '2026-08-17T18:00:00Z',
    systemQuotedCharge: 6.50,
    actualCourierCharge: 6.50,
    isWeightDiscrepancy: false,
    trackingEvents: [
      { timestamp: '2026-08-17 08:30', location: 'J&T Subang Jaya Hub DC', status: 'Out for Delivery', description: 'Parcel is out for delivery with courier rider (Ahmad Fauzi - 012-9988112)' },
      { timestamp: '2026-08-16 22:15', location: 'J&T Gateway Shah Alam (CDC)', status: 'In Transit', description: 'Processed at central distribution sorting machine.' },
      { timestamp: '2026-08-16 16:45', location: 'JW Studio Warehouse Hub Subang', status: 'Picked Up', description: 'Shipment handed over to J&T Courier Driver.' },
      { timestamp: '2026-08-16 11:00', location: 'JW Studio Merchant Portal', status: 'AWB Printed', description: 'Shipping label created and ready for packing.' }
    ]
  },
  {
    id: 'shp_my_102',
    orderId: 'ord_102',
    orderNumber: 'ORD-2026-9042',
    customerName: 'Lim Wei Jian',
    items: [
      { sku: 'AUD-HD-99', name: 'Acoustic Studio ANC Wireless Headphones', qty: 1, unitPrice: 380.00, weightKg: 0.85, isHazardousLiquidBattery: true }
    ],
    totalWeightKg: 1.10,
    volumetricWeightKg: 1.60,
    dimensionsCm: { length: 25, width: 20, height: 16 },
    courier: 'posmalaysia',
    serviceType: 'POS_DOMESTIC (Pos Laju Air)',
    trackingNumber: 'PL892019283MY',
    routingSortCode: 'BKI-A1-02',
    status: 'in_transit',
    paymentMode: 'prepaid',
    isEastMalaysiaAirRestricted: true,
    postcodeValidationStatus: 'valid',
    createdAt: '2026-08-15T14:20:00Z',
    awbGeneratedAt: '2026-08-15T15:10:00Z',
    dispatchedAt: '2026-08-15T18:00:00Z',
    estimatedDeliveryDate: '2026-08-18T17:00:00Z',
    systemQuotedCharge: 18.50,
    actualCourierCharge: 21.00,
    isWeightDiscrepancy: true,
    trackingEvents: [
      { timestamp: '2026-08-17 06:15', location: 'Pos Laju Kota Kinabalu Integrated Parcel Centre (IPC)', status: 'In Transit', description: 'Arrived at destination airport sorting facility (Sabah).' },
      { timestamp: '2026-08-16 11:30', location: 'KLIA Cargo Air Terminal (KUL)', status: 'Departed Facility', description: 'Flight MH2614 departed KLIA to BKI.' },
      { timestamp: '2026-08-15 18:00', location: 'Pos Laju Shah Alam Hub', status: 'Picked Up', description: 'Parcel accepted at Pos Malaysia distribution hub.' }
    ]
  },
  {
    id: 'shp_my_103',
    orderId: 'ord_103',
    orderNumber: 'ORD-2026-9043',
    customerName: 'Muhammad Hafizuddin',
    items: [
      { sku: 'TEE-OVER-BLK', name: 'Heavyweight Minimalist Graphic Tee (Black - XL)', qty: 3, unitPrice: 65.00, weightKg: 0.90, isHazardousLiquidBattery: false }
    ],
    totalWeightKg: 0.95,
    volumetricWeightKg: 0.80,
    dimensionsCm: { length: 30, width: 22, height: 6 },
    courier: 'ninjavan',
    serviceType: 'Ninja Standard (COD Available)',
    trackingNumber: 'NVMY1098234710',
    routingSortCode: 'JHR-DC-01',
    status: 'failed_delivery',
    paymentMode: 'cod',
    codAmount: 195.00,
    isEastMalaysiaAirRestricted: false,
    postcodeValidationStatus: 'valid',
    createdAt: '2026-08-15T09:00:00Z',
    awbGeneratedAt: '2026-08-15T09:40:00Z',
    dispatchedAt: '2026-08-15T15:30:00Z',
    systemQuotedCharge: 7.50,
    actualCourierCharge: 7.50,
    isWeightDiscrepancy: false,
    exceptionReason: 'NITC (Not in Touch with Customer) - Recipient phone unreachable after 3 attempts. Cash not ready.',
    exceptionAttempts: 2,
    trackingEvents: [
      { timestamp: '2026-08-17 04:30', location: 'Ninja Van Johor Bahru Station', status: 'Delivery Failed (NITC)', description: 'Attempted delivery failed: Buyer did not answer call for COD collection (Attempt 2/3).' },
      { timestamp: '2026-08-16 11:20', location: 'Ninja Van Johor Bahru Station', status: 'Delivery Failed (NITC)', description: 'Buyer requested reschedule to next day.' },
      { timestamp: '2026-08-15 15:30', location: 'JW Studio Central Warehouse', status: 'Picked Up', description: 'Parcel collected by Ninja Driver.' }
    ]
  },
  {
    id: 'shp_my_104',
    orderId: 'ord_104',
    orderNumber: 'ORD-2026-9044',
    customerName: 'Tan Siew Ling',
    items: [
      { sku: 'BOT-CER-500', name: 'Double-wall Vacuum Thermal Flask 500ml', qty: 1, unitPrice: 79.00, weightKg: 0.45, isHazardousLiquidBattery: false }
    ],
    totalWeightKg: 0.50,
    volumetricWeightKg: 0.60,
    dimensionsCm: { length: 24, width: 10, height: 10 },
    courier: 'flashexpress',
    serviceType: 'Flash Standard Door-to-Door',
    trackingNumber: 'FL68291029MY',
    routingSortCode: 'PNG-01-N',
    status: 'delivered',
    paymentMode: 'prepaid',
    isEastMalaysiaAirRestricted: false,
    postcodeValidationStatus: 'valid',
    createdAt: '2026-08-14T11:00:00Z',
    awbGeneratedAt: '2026-08-14T11:30:00Z',
    dispatchedAt: '2026-08-14T17:00:00Z',
    deliveredAt: '2026-08-16T14:15:00Z',
    systemQuotedCharge: 5.80,
    actualCourierCharge: 5.80,
    isWeightDiscrepancy: false,
    trackingEvents: [
      { timestamp: '2026-08-16 14:15', location: 'George Town, Penang', status: 'Delivered', description: 'Delivered and signed by recipient: TAN (POD Photo Uploaded).' },
      { timestamp: '2026-08-16 09:10', location: 'Flash Express Penang Hub', status: 'Out for Delivery', description: 'Out for delivery.' },
      { timestamp: '2026-08-15 03:00', location: 'Flash Express Ipoh Gateway', status: 'In Transit', description: 'Linehaul transit.' },
      { timestamp: '2026-08-14 17:00', location: 'JW Studio Merchant Hub', status: 'Picked Up', description: 'Flash Express driver picked up package.' }
    ]
  },
  {
    id: 'shp_my_105',
    orderId: 'ord_105',
    orderNumber: 'ORD-2026-9045',
    customerName: 'Chong Chee Keong',
    items: [
      { sku: 'LUM-SET-03', name: 'Complete Hydration Skincare Routine Bundle', qty: 1, unitPrice: 249.00, weightKg: 0.85, isHazardousLiquidBattery: false }
    ],
    totalWeightKg: 0.90,
    volumetricWeightKg: 1.10,
    dimensionsCm: { length: 28, width: 18, height: 11 },
    courier: 'easyparcel',
    serviceType: 'EP_BEST_RATE (PosLaju via EasyParcel)',
    trackingNumber: 'EP88291029MY',
    routingSortCode: 'KCH-01-E',
    status: 'ready_to_ship',
    paymentMode: 'prepaid',
    isEastMalaysiaAirRestricted: false,
    postcodeValidationStatus: 'valid',
    createdAt: '2026-08-17T02:00:00Z',
    awbGeneratedAt: '2026-08-17T02:15:00Z',
    systemQuotedCharge: 14.80,
    isWeightDiscrepancy: false,
    trackingEvents: [
      { timestamp: '2026-08-17 02:15', location: 'JW Studio Merchant Portal', status: 'AWB Printed', description: 'Waybill generated via EasyParcel API. Deducted RM 14.80 from wallet.' }
    ]
  },
  {
    id: 'shp_my_106',
    orderId: 'ord_106',
    orderNumber: 'ORD-2026-9046',
    customerName: 'Farah Diana Binti Razak',
    items: [
      { sku: 'LUM-SER-01', name: 'Luminance Silk Hydration Serum (50ml)', qty: 1, unitPrice: 89.00, weightKg: 0.25, isHazardousLiquidBattery: false }
    ],
    totalWeightKg: 0.35,
    volumetricWeightKg: 0.30,
    dimensionsCm: { length: 18, width: 12, height: 7 },
    courier: 'jnt',
    serviceType: 'EZ (Standard Express)',
    trackingNumber: 'JNT6019288812MY',
    routingSortCode: 'KUA-01-JNT',
    status: 'pending',
    paymentMode: 'prepaid',
    isEastMalaysiaAirRestricted: false,
    postcodeValidationStatus: 'valid',
    createdAt: '2026-08-17T04:10:00Z',
    systemQuotedCharge: 6.50,
    isWeightDiscrepancy: false,
    trackingEvents: [
      { timestamp: '2026-08-17 04:10', location: 'JW Studio Order Desk', status: 'Pending Fulfillment', description: 'Order created, awaiting AWB generation and packing.' }
    ]
  }
];

export const INITIAL_PICKUP_REQUESTS: PickupRequest[] = [
  {
    id: 'pkup_01',
    pickupRefNo: 'PKUP-JNT-20260817-001',
    courier: 'jnt',
    pickupDate: '2026-08-17',
    timeSlot: '14:00 - 18:00',
    totalParcels: 14,
    totalWeightKg: 18.5,
    vehicleType: 'van_1ton',
    pickupAddress: 'JW Studio Central Warehouse, No. 18, Jalan TP 5, Taman Perindustrian UEP, 47600 Subang Jaya',
    contactPerson: 'Zul (Logistics Supervisor)',
    contactPhone: '+0142244200',
    driverNotes: 'Loading bay 2. Ring bell on arrival.',
    status: 'confirmed',
    assignedDriver: {
      name: 'Khairul Anwar',
      phone: '+60 19-281 9028',
      vehiclePlate: 'WXY 8812'
    },
    createdAt: '2026-08-17T01:30:00Z'
  },
  {
    id: 'pkup_02',
    pickupRefNo: 'PKUP-NV-20260817-002',
    courier: 'ninjavan',
    pickupDate: '2026-08-17',
    timeSlot: '10:00 - 13:00',
    totalParcels: 8,
    totalWeightKg: 9.2,
    vehicleType: 'car',
    pickupAddress: 'JW Studio Shah Alam Hub, Seksyen 22, 40300 Shah Alam',
    contactPerson: 'Alex (Warehouse Lead)',
    contactPhone: '+0142244200',
    driverNotes: 'Doorstep pickup at office lobby.',
    status: 'completed',
    assignedDriver: {
      name: 'Ravi Kumar',
      phone: '+60 17-381 2910',
      vehiclePlate: 'BRP 4912'
    },
    createdAt: '2026-08-16T18:00:00Z'
  }
];

export const INITIAL_BILLING_RECONCILIATIONS: BillingReconciliationRecord[] = [
  {
    id: 'rec_01',
    invoiceNumber: 'INV-JNT-2026-07-MY',
    billingMonth: 'July 2026',
    courier: 'jnt',
    trackingNumber: 'JNT6018273619MY',
    orderNumber: 'ORD-2026-8812',
    systemWeightKg: 1.20,
    courierBilledWeightKg: 3.40,
    systemRateRM: 6.50,
    courierBilledRateRM: 14.80,
    sstTaxRM: 0.89,
    varianceRM: 9.19,
    discrepancyType: 'volumetric_surge',
    reconciliationStatus: 'disputed'
  },
  {
    id: 'rec_02',
    invoiceNumber: 'INV-POS-2026-07',
    billingMonth: 'July 2026',
    courier: 'posmalaysia',
    trackingNumber: 'PL882910283MY',
    orderNumber: 'ORD-2026-8815',
    systemWeightKg: 0.85,
    courierBilledWeightKg: 0.85,
    systemRateRM: 14.50,
    courierBilledRateRM: 14.50,
    sstTaxRM: 0.87,
    varianceRM: 0.00,
    discrepancyType: 'normal',
    reconciliationStatus: 'matched'
  },
  {
    id: 'rec_03',
    invoiceNumber: 'INV-NV-2026-07',
    billingMonth: 'July 2026',
    courier: 'ninjavan',
    trackingNumber: 'NVMY0019283746',
    orderNumber: 'ORD-2026-8820',
    systemWeightKg: 0.50,
    courierBilledWeightKg: 0.50,
    systemRateRM: 7.50,
    courierBilledRateRM: 10.50,
    sstTaxRM: 0.63,
    varianceRM: 3.63,
    discrepancyType: 'surcharge_mismatch',
    reconciliationStatus: 'disputed'
  },
  {
    id: 'rec_04',
    invoiceNumber: 'INV-EP-2026-07',
    billingMonth: 'July 2026',
    courier: 'easyparcel',
    trackingNumber: 'EP7719283610MY',
    orderNumber: 'ORD-2026-8825',
    systemWeightKg: 1.10,
    courierBilledWeightKg: 1.10,
    systemRateRM: 5.90,
    courierBilledRateRM: 5.90,
    sstTaxRM: 0.35,
    varianceRM: 0.00,
    discrepancyType: 'normal',
    reconciliationStatus: 'matched'
  }
];

export const INITIAL_SHIPPING_RATES: GeneralShippingRate[] = [
  {
    id: 'rate_west_my_weight',
    name: 'West Malaysia Weight-Based Tier Rate',
    zoneRegion: 'peninsular',
    logisticType: 'all',
    calculationMode: 'weight',
    rateType: 'custom',
    tiers: [
      { id: 'tier_1', min: 0, max: 2, cost: 8 },
      { id: 'tier_2', min: 2, max: 8, cost: 12 },
      { id: 'tier_3', min: 8, max: 20, cost: 20 }
    ],
    subsequentRate: 1.0,
    subsequentCost: 2.0,
    freeShippingThreshold: 120,
    estimatedDays: '1 - 3 Working Days',
    isActive: true,
    notes: 'Default tiered weight rate for all Peninsular Malaysia deliveries. Free shipping over RM 120.',
    createdAt: '2026-08-01 10:00:00',
    updatedAt: '2026-08-15 14:30:00'
  },
  {
    id: 'rate_east_my_air',
    name: 'East Malaysia (Sabah & Sarawak) Air Tier Rate',
    zoneRegion: 'east_malaysia',
    logisticType: 'all',
    calculationMode: 'weight',
    rateType: 'custom',
    tiers: [
      { id: 'tier_em_1', min: 0, max: 1, cost: 15 },
      { id: 'tier_em_2', min: 1, max: 3, cost: 28 },
      { id: 'tier_em_3', min: 3, max: 10, cost: 65 }
    ],
    subsequentRate: 1.0,
    subsequentCost: 12.0,
    freeShippingThreshold: 250,
    estimatedDays: '3 - 5 Working Days',
    isActive: true,
    notes: 'Cross-water Air Freight to Sabah and Sarawak with subsequent 1kg billing.',
    createdAt: '2026-08-02 11:20:00',
    updatedAt: '2026-08-14 09:15:00'
  },
  {
    id: 'rate_quantity_fashion',
    name: 'Apparel & Merch Item Quantity Rate',
    zoneRegion: 'peninsular',
    logisticType: 'jnt',
    calculationMode: 'quantity',
    rateType: 'custom',
    tiers: [
      { id: 'tier_qty_1', min: 0, max: 2, cost: 6 },
      { id: 'tier_qty_2', min: 2, max: 5, cost: 10 },
      { id: 'tier_qty_3', min: 5, max: 10, cost: 15 }
    ],
    subsequentRate: 1,
    subsequentCost: 1.5,
    freeShippingThreshold: 150,
    estimatedDays: '1 - 2 Working Days',
    isActive: true,
    notes: 'Item count-based postage for fashion apparel and lightweight goods.',
    createdAt: '2026-08-05 16:45:00',
    updatedAt: '2026-08-12 18:00:00'
  },
  {
    id: 'rate_flat_pos_standard',
    name: 'Flat Rate Standard Pos (Per Order)',
    zoneRegion: 'all',
    logisticType: 'posmalaysia',
    calculationMode: 'per_order',
    rateType: 'flat',
    tiers: [],
    flatRateCost: 7.0,
    freeShippingThreshold: 90,
    estimatedDays: '2 - 4 Working Days',
    isActive: true,
    notes: 'Fixed RM 7.00 per order across all Malaysia postal zones.',
    createdAt: '2026-08-10 09:00:00',
    updatedAt: '2026-08-16 11:00:00'
  }
];
