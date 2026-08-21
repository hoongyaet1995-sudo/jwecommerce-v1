import { 
  MarketplaceConnection, 
  MarketplaceOrder, 
  MarketplaceInventoryItem, 
  MarketplaceSyncLog 
} from '../types';

export const INITIAL_MARKETPLACE_CONNECTIONS: MarketplaceConnection[] = [
  {
    id: 'conn-tiktok-01',
    platform: 'tiktok',
    shopName: 'Aurora Audio Official Shop',
    shopId: 'TTS_MY_748291038',
    sellerId: 'MY_SELLER_884920',
    region: 'MY',
    appKey: '6a8f920c81bf44a1980',
    appSecret: '••••••••••••••••••••••••••••3a9b',
    accessToken: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    refreshToken: '7d793037a0760186574b0282f2f435e7',
    tokenExpiresAt: new Date(Date.now() + 26 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'connected',
    autoSyncStock: true,
    autoPullOrders: true,
    webhookRegistered: true,
    webhookSecret: 'whsec_tt_89201492038194',
    syncIntervalMinutes: 5,
    scopes: ['orders.read_write', 'fulfillment.write', 'inventory.sync', 'webhooks.receive'],
    lastSyncAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
    orderCount: 142,
    productCount: 18,
    priceMarkupPercent: 3, // 3% markup for TikTok platform fee
    stockBufferPercent: 5,
    defaultCourier: 'J&T Express (TikTok Shipping)',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=120&auto=format&fit=crop&q=80'
  },
  {
    id: 'conn-shopee-01',
    platform: 'shopee',
    shopName: 'Aurora Audio Flagship Mall',
    shopId: '293849102',
    partnerId: '849201',
    sellerId: 'aurora_audio.os',
    region: 'MY',
    appKey: '4f81c9a03b554e2d83',
    appSecret: '••••••••••••••••••••••••••••9e21',
    accessToken: 'shopee_live_auth_91823910394857201948372',
    refreshToken: 'shopee_refresh_token_09823419082391',
    tokenExpiresAt: new Date(Date.now() + 29 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'connected',
    autoSyncStock: true,
    autoPullOrders: true,
    webhookRegistered: true,
    webhookSecret: 'whsec_shopee_3948201948201',
    syncIntervalMinutes: 5,
    scopes: ['orders.read_write', 'fulfillment.write', 'inventory.sync', 'pricing.manage', 'webhooks.receive'],
    lastSyncAt: new Date(Date.now() - 1 * 60 * 1000).toISOString(),
    orderCount: 389,
    productCount: 24,
    priceMarkupPercent: 0,
    stockBufferPercent: 10,
    defaultCourier: 'Shopee Supported Logistics (SPX)',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=120&auto=format&fit=crop&q=80'
  },
  {
    id: 'conn-lazada-01',
    platform: 'lazada',
    shopName: 'Aurora Audio LazMall Store',
    shopId: 'MY102938482',
    sellerId: 'seller_laz_aurora_my',
    region: 'MY',
    appKey: '10928301',
    appSecret: '••••••••••••••••••••••••••••7c44',
    accessToken: 'lazada_sec_tok_84920194830192847291',
    refreshToken: 'lazada_ref_tok_10294820192847',
    tokenExpiresAt: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'connected',
    autoSyncStock: true,
    autoPullOrders: true,
    webhookRegistered: true,
    webhookSecret: 'whsec_laz_9847201928374',
    syncIntervalMinutes: 5,
    scopes: ['orders.read_write', 'fulfillment.write', 'inventory.sync'],
    lastSyncAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
    orderCount: 98,
    productCount: 16,
    priceMarkupPercent: 0,
    stockBufferPercent: 5,
    defaultCourier: 'Lazada Logistics Express (LEX)',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=120&auto=format&fit=crop&q=80'
  },
  {
    id: 'conn-shopify-01',
    platform: 'shopify',
    shopName: 'Aurora Global Direct Store',
    shopId: 'aurora-global-direct.myshopify.com',
    sellerId: 'admin_shopify_primary',
    region: 'GLOBAL',
    appKey: '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',
    appSecret: '••••••••••••••••••••••••••••1f90',
    accessToken: 'shpca_live_token_77889900112233445566',
    refreshToken: 'shopify_long_lived_secret',
    tokenExpiresAt: new Date(Date.now() + 360 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'connected',
    autoSyncStock: true,
    autoPullOrders: true,
    webhookRegistered: true,
    webhookSecret: 'whsec_shopify_48201948201948',
    syncIntervalMinutes: 1,
    scopes: ['orders.read_write', 'fulfillment.write', 'inventory.sync', 'webhooks.receive'],
    lastSyncAt: new Date(Date.now() - 2 * 60 * 1000).toISOString(),
    orderCount: 215,
    productCount: 28,
    priceMarkupPercent: 0,
    stockBufferPercent: 0,
    defaultCourier: 'DHL Express Worldwide / FedEx',
    environment: 'production',
    shopAvatar: 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=120&auto=format&fit=crop&q=80'
  }
];

export const INITIAL_MARKETPLACE_ORDERS: MarketplaceOrder[] = [
  {
    id: 'mord-tt-901',
    orderNumber: 'ORD-TT-578491',
    channel: 'tiktok',
    channelStoreId: 'TTS_MY_748291038',
    channelStoreName: 'Aurora Audio Official Shop',
    channelOrderId: '578491029384729183',
    marketplaceBuyerUsername: '@haikal_audiophile',
    channelStatus: 'AWAITING_COLLECTION',
    slaShipDeadline: new Date(Date.now() + 3.5 * 60 * 60 * 1000).toISOString(), // 3.5 hours left (URGENT)
    isSlaBreachRisk: true,
    airwayBillGenerated: true,
    airwayBillUrl: 'https://labels.tiktokcdn.com/awb/578491029384729183.pdf',
    pickupType: 'dropoff',
    allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
    packedBy: 'Staff Hazim',
    scannedItemsCount: 1,
    createdAt: new Date(Date.now() - 21 * 60 * 60 * 1000).toISOString(),
    customerId: 'cust-tt-01',
    customerName: 'Ahmad Haikal Bin Razak',
    customerEmail: 'haikal.razak@gmail.com',
    customerPhone: '+60 12-883 9102',
    paymentGateway: 'Credit Card',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'processing',
    currency: 'MYR',
    subtotal: 1899.00,
    taxAmount: 0,
    shippingAmount: 0,
    discountAmount: 100.00,
    totalAmount: 1799.00,
    trackingNumber: 'JT6082918302MY',
    trackingCarrier: 'J&T Express (TikTok Shipping)',
    shippingAddress: {
      id: 'addr-tt-01',
      isDefault: true,
      type: 'shipping',
      firstName: 'Ahmad Haikal',
      lastName: 'Bin Razak',
      company: 'Petronas Digital',
      address1: 'Level 14, Tower 3, KLCC',
      city: 'Kuala Lumpur',
      province: 'Wilayah Persekutuan',
      country: 'Malaysia',
      postalCode: '50088',
      phone: '+60 12-883 9102'
    },
    billingAddress: {
      id: 'addr-tt-01-b',
      isDefault: true,
      type: 'billing',
      firstName: 'Ahmad Haikal',
      lastName: 'Bin Razak',
      address1: 'Level 14, Tower 3, KLCC',
      city: 'Kuala Lumpur',
      province: 'Wilayah Persekutuan',
      country: 'Malaysia',
      postalCode: '50088',
      phone: '+60 12-883 9102'
    },
    customerNote: 'Please bubble wrap thickly! It is a birthday gift.',
    internalNote: 'TikTok Campaign Voucher Auto-applied ($100 subsidised by TikTok)',
    items: [
      {
        id: 'item-m-01',
        productId: 'prod-01',
        variantId: 'var-01-blk',
        title: 'Aurora ANC Wireless Headphones',
        variantTitle: 'Midnight Black / Titanium',
        sku: 'AUR-ANC-01-BLK',
        price: 1899.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&auto=format&fit=crop&q=80',
        weightGrams: 420
      }
    ],
    timeline: [
      {
        id: 'evt-1',
        timestamp: new Date(Date.now() - 21 * 60 * 60 * 1000).toISOString(),
        type: 'created',
        title: 'TikTok Shop Order Inbound Received',
        description: 'Order synchronized via TikTok Open API Webhook [Event: ORDER_STATUS_CHANGE]'
      },
      {
        id: 'evt-2',
        timestamp: new Date(Date.now() - 20 * 60 * 60 * 1000).toISOString(),
        type: 'paid',
        title: 'Escrow Payment Confirmed by TikTok',
        description: 'Payment captured securely in TikTok Shop Escrow'
      },
      {
        id: 'evt-3',
        timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
        type: 'fulfillment_updated',
        title: 'Airway Bill Generated (J&T Express)',
        description: 'AWB Tracking #JT6082918302MY registered with J&T Express pickup manifest'
      }
    ]
  },
  {
    id: 'mord-sp-802',
    orderNumber: 'ORD-SP-240817U9',
    channel: 'shopee',
    channelStoreId: '293849102',
    channelStoreName: 'Aurora Audio Flagship Mall',
    channelOrderId: '240817U9X381A2',
    marketplaceBuyerUsername: 'chong_audiophile_my',
    channelStatus: 'READY_TO_SHIP',
    slaShipDeadline: new Date(Date.now() + 18 * 60 * 60 * 1000).toISOString(), // 18 hours left
    isSlaBreachRisk: false,
    airwayBillGenerated: true,
    airwayBillUrl: 'https://spx.shopee.com.my/awb/240817U9X381A2',
    pickupType: 'pickup',
    allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
    packedBy: 'Staff Mei Ling',
    scannedItemsCount: 2,
    createdAt: new Date(Date.now() - 14 * 60 * 60 * 1000).toISOString(),
    customerId: 'cust-sp-02',
    customerName: 'Marcus Chong Wei Lun',
    customerEmail: 'marcus.chong@outlook.com',
    customerPhone: '+60 16-449 2011',
    paymentGateway: 'Credit Card',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'processing',
    currency: 'MYR',
    subtotal: 1398.00,
    taxAmount: 0,
    shippingAmount: 0,
    discountAmount: 50.00,
    totalAmount: 1348.00,
    trackingNumber: 'SPXMY0482918402',
    trackingCarrier: 'Shopee Supported Logistics (SPX Express)',
    shippingAddress: {
      id: 'addr-sp-02',
      isDefault: true,
      type: 'shipping',
      firstName: 'Marcus',
      lastName: 'Chong Wei Lun',
      address1: 'No 45, Jalan Setia Nusantara U13/22',
      address2: 'Setia Eco Park',
      city: 'Shah Alam',
      province: 'Selangor',
      country: 'Malaysia',
      postalCode: '40170',
      phone: '+60 16-449 2011'
    },
    billingAddress: {
      id: 'addr-sp-02-b',
      isDefault: true,
      type: 'billing',
      firstName: 'Marcus',
      lastName: 'Chong Wei Lun',
      address1: 'No 45, Jalan Setia Nusantara U13/22',
      city: 'Shah Alam',
      province: 'Selangor',
      country: 'Malaysia',
      postalCode: '40170',
      phone: '+60 16-449 2011'
    },
    customerNote: 'Leave at guardhouse if not home',
    items: [
      {
        id: 'item-m-02',
        productId: 'prod-02',
        variantId: 'var-02-wht',
        title: 'Aurora True Wireless Earbuds Pro',
        variantTitle: 'Ceramic White / Silver',
        sku: 'AUR-TWS-PRO-WHT',
        price: 699.00,
        quantity: 2,
        image: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=300&auto=format&fit=crop&q=80',
        weightGrams: 180
      }
    ],
    timeline: [
      {
        id: 'evt-sp-1',
        timestamp: new Date(Date.now() - 14 * 60 * 60 * 1000).toISOString(),
        type: 'created',
        title: 'Shopee Order Synced',
        description: 'Auto-pulled from Shopee v2.order.get_order_detail'
      },
      {
        id: 'evt-sp-2',
        timestamp: new Date(Date.now() - 6 * 60 * 60 * 1000).toISOString(),
        type: 'fulfillment_updated',
        title: 'Item Picked & Verified',
        description: 'Barcode verified by Mei Ling at Bin A-02-C1'
      }
    ]
  },
  {
    id: 'mord-lz-703',
    orderNumber: 'ORD-LZ-849201',
    channel: 'lazada',
    channelStoreId: 'MY102938482',
    channelStoreName: 'Aurora Audio LazMall Store',
    channelOrderId: '849201928471920',
    marketplaceBuyerUsername: 'siti_nurhaliza_fan',
    channelStatus: 'UNPACKED',
    slaShipDeadline: new Date(Date.now() + 28 * 60 * 60 * 1000).toISOString(), // 28 hours left
    isSlaBreachRisk: false,
    airwayBillGenerated: false,
    pickupType: 'dropoff',
    allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
    scannedItemsCount: 0,
    createdAt: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
    customerId: 'cust-lz-03',
    customerName: 'Nurul Izzah Binti Kamaruddin',
    customerEmail: 'izzah.k@gmail.com',
    customerPhone: '+60 19-332 1948',
    paymentGateway: 'Credit Card',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'unfulfilled',
    currency: 'MYR',
    subtotal: 1299.00,
    taxAmount: 0,
    shippingAmount: 0,
    discountAmount: 0,
    totalAmount: 1299.00,
    shippingAddress: {
      id: 'addr-lz-03',
      isDefault: true,
      type: 'shipping',
      firstName: 'Nurul Izzah',
      lastName: 'Binti Kamaruddin',
      address1: 'Unit B-12-08, Residensi Suasana',
      address2: 'Damansara Damai',
      city: 'Petaling Jaya',
      province: 'Selangor',
      country: 'Malaysia',
      postalCode: '47830',
      phone: '+60 19-332 1948'
    },
    billingAddress: {
      id: 'addr-lz-03-b',
      isDefault: true,
      type: 'billing',
      firstName: 'Nurul Izzah',
      lastName: 'Binti Kamaruddin',
      address1: 'Unit B-12-08, Residensi Suasana',
      city: 'Petaling Jaya',
      province: 'Selangor',
      country: 'Malaysia',
      postalCode: '47830',
      phone: '+60 19-332 1948'
    },
    items: [
      {
        id: 'item-m-03',
        productId: 'prod-03',
        variantId: 'var-03-slv',
        title: 'Aurora Studio Hi-Fi DAC & Amp V2',
        variantTitle: 'Brushed Silver / Dual ESS Sabre',
        sku: 'AUR-DAC-V2-SLV',
        price: 1299.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=300&auto=format&fit=crop&q=80',
        weightGrams: 850
      }
    ],
    timeline: [
      {
        id: 'evt-lz-1',
        timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
        type: 'created',
        title: 'Lazada Open API Webhook Received',
        description: 'New LazMall order recorded (Status: UNPACKED)'
      }
    ]
  },
  {
    id: 'mord-sh-604',
    orderNumber: 'ORD-SH-10492',
    channel: 'shopify',
    channelStoreId: 'aurora-global-direct.myshopify.com',
    channelStoreName: 'Aurora Global Direct Store',
    channelOrderId: '5849201948',
    marketplaceBuyerUsername: 'david.miller.ny',
    channelStatus: 'UNFULFILLED',
    slaShipDeadline: new Date(Date.now() + 42 * 60 * 60 * 1000).toISOString(),
    isSlaBreachRisk: false,
    airwayBillGenerated: true,
    airwayBillUrl: 'https://dhl.com/labels/9482910394.pdf',
    pickupType: 'pickup',
    allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
    packedBy: 'Staff Alex',
    scannedItemsCount: 1,
    createdAt: new Date(Date.now() - 8 * 60 * 60 * 1000).toISOString(),
    customerId: 'cust-sh-04',
    customerName: 'David Miller',
    customerEmail: 'david.miller@studiobrooklyn.com',
    customerPhone: '+1 (917) 555-0192',
    paymentGateway: 'Stripe',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'processing',
    currency: 'USD',
    subtotal: 549.00,
    taxAmount: 43.92,
    shippingAmount: 25.00,
    discountAmount: 0,
    totalAmount: 617.92,
    trackingNumber: 'DHL9482910394US',
    trackingCarrier: 'DHL Express Worldwide',
    shippingAddress: {
      id: 'addr-sh-04',
      isDefault: true,
      type: 'shipping',
      firstName: 'David',
      lastName: 'Miller',
      company: 'Brooklyn Soundworks',
      address1: '240 Kent Avenue, Suite 4B',
      city: 'Brooklyn',
      province: 'NY',
      country: 'United States',
      postalCode: '11249',
      phone: '+1 (917) 555-0192'
    },
    billingAddress: {
      id: 'addr-sh-04-b',
      isDefault: true,
      type: 'billing',
      firstName: 'David',
      lastName: 'Miller',
      address1: '240 Kent Avenue, Suite 4B',
      city: 'Brooklyn',
      province: 'NY',
      country: 'United States',
      postalCode: '11249',
      phone: '+1 (917) 555-0192'
    },
    items: [
      {
        id: 'item-m-04',
        productId: 'prod-04',
        variantId: 'var-04-wal',
        title: 'Aurora Studio Reference Monitors (Pair)',
        variantTitle: 'Solid Walnut / Kevlar Cones',
        sku: 'AUR-ST-MON-WAL',
        price: 549.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=300&auto=format&fit=crop&q=80',
        weightGrams: 6200
      }
    ],
    timeline: [
      {
        id: 'evt-sh-1',
        timestamp: new Date(Date.now() - 8 * 60 * 60 * 1000).toISOString(),
        type: 'created',
        title: 'Shopify Webhook `orders/create` Triggered',
        description: 'Direct international order received via Shopify Admin API'
      }
    ]
  },
  {
    id: 'mord-tt-905',
    orderNumber: 'ORD-TT-578499',
    channel: 'tiktok',
    channelStoreId: 'TTS_MY_748291038',
    channelStoreName: 'Aurora Audio Official Shop',
    channelOrderId: '578499019284729199',
    marketplaceBuyerUsername: '@kenji_gamer',
    channelStatus: 'IN_TRANSIT',
    slaShipDeadline: new Date(Date.now() - 10 * 60 * 60 * 1000).toISOString(),
    isSlaBreachRisk: false,
    airwayBillGenerated: true,
    pickupType: 'dropoff',
    allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
    createdAt: new Date(Date.now() - 48 * 60 * 60 * 1000).toISOString(),
    customerId: 'cust-tt-05',
    customerName: 'Kenji Tan Jin Wei',
    customerEmail: 'kenji.tan@gmail.com',
    customerPhone: '+60 17-293 8471',
    paymentGateway: 'Credit Card',
    paymentGatewayStatus: 'paid',
    fulfillmentStatus: 'shipped',
    currency: 'MYR',
    subtotal: 1899.00,
    taxAmount: 0,
    shippingAmount: 0,
    discountAmount: 50.00,
    totalAmount: 1849.00,
    trackingNumber: 'JT6082998811MY',
    trackingCarrier: 'J&T Express (TikTok Shipping)',
    shippingAddress: {
      id: 'addr-tt-05',
      isDefault: true,
      type: 'shipping',
      firstName: 'Kenji',
      lastName: 'Tan Jin Wei',
      address1: 'No 12, Lorong Batu Nilam 21A',
      city: 'Klang',
      province: 'Selangor',
      country: 'Malaysia',
      postalCode: '41200',
      phone: '+60 17-293 8471'
    },
    billingAddress: {
      id: 'addr-tt-05-b',
      isDefault: true,
      type: 'billing',
      firstName: 'Kenji',
      lastName: 'Tan Jin Wei',
      address1: 'No 12, Lorong Batu Nilam 21A',
      city: 'Klang',
      province: 'Selangor',
      country: 'Malaysia',
      postalCode: '41200',
      phone: '+60 17-293 8471'
    },
    items: [
      {
        id: 'item-m-05',
        productId: 'prod-01',
        variantId: 'var-01-sil',
        title: 'Aurora ANC Wireless Headphones',
        variantTitle: 'Silver Frost / Aluminum',
        sku: 'AUR-ANC-01-SIL',
        price: 1899.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=300&auto=format&fit=crop&q=80',
        weightGrams: 420
      }
    ],
    timeline: [
      {
        id: 'evt-5-1',
        timestamp: new Date(Date.now() - 48 * 60 * 60 * 1000).toISOString(),
        type: 'created',
        title: 'Order Placed on TikTok LIVE Stream',
        description: 'Affiliate Creator: @techreview_my'
      },
      {
        id: 'evt-5-2',
        timestamp: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(),
        type: 'fulfillment_updated',
        title: 'Handed Over to Courier at Hub',
        description: 'J&T Express Dropoff scan confirmed'
      }
    ]
  }
];

export const INITIAL_MARKETPLACE_INVENTORY: MarketplaceInventoryItem[] = [
  {
    id: 'inv-01',
    masterSku: 'AUR-ANC-01-BLK',
    barcode: '8809281938012',
    productName: 'Aurora ANC Wireless Headphones',
    variantName: 'Midnight Black / Titanium',
    imageUrl: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&auto=format&fit=crop&q=80',
    warehouseLocation: 'Zone A - Rack 01 - Bin A1',
    totalPhysicalStock: 150,
    reservedStock: 18,
    safetyStock: 10,
    availableStock: 122, // 150 - 18 - 10
    reorderPoint: 30,
    costPrice: 850.00,
    sellingPrice: 1899.00,
    autoSyncEnabled: true,
    lastBroadcastAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
    channelMappings: {
      tiktok: {
        isLinked: true,
        channelProductId: 'TTS_PRD_98492019',
        channelSkuId: 'TTS_SKU_10293848',
        channelStock: 122,
        price: 1955.00, // +3% markup
        lastSyncedAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopee: {
        isLinked: true,
        channelItemId: 'SP_ITEM_2938491028',
        channelModelId: 'SP_MOD_84920194',
        channelStock: 122,
        price: 1899.00,
        lastSyncedAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      lazada: {
        isLinked: true,
        channelSellerSku: 'LZ-AUR-ANC-BLK',
        channelItemId: 'LZ_ITEM_98372819',
        channelStock: 122,
        price: 1899.00,
        lastSyncedAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopify: {
        isLinked: true,
        channelVariantId: 'gid://shopify/ProductVariant/449201928471',
        channelProductId: 'gid://shopify/Product/8492019482',
        channelStock: 122,
        price: 499.00,
        lastSyncedAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      }
    },
    historyLog: [
      {
        id: 'hl-1',
        timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
        action: 'ORDER_RESERVATION',
        change: -1,
        resultingStock: 122,
        user: 'TikTok Webhook Listener',
        reason: 'Order #ORD-TT-578491 placed by customer'
      },
      {
        id: 'hl-2',
        timestamp: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(),
        action: 'INBOUND_RECEIVING',
        change: +50,
        resultingStock: 123,
        user: 'Warehouse Lead (Hazim)',
        reason: 'Factory PO #PO-2026-081 Arrival Inspection Pass'
      }
    ]
  },
  {
    id: 'inv-02',
    masterSku: 'AUR-ANC-01-SIL',
    barcode: '8809281938029',
    productName: 'Aurora ANC Wireless Headphones',
    variantName: 'Silver Frost / Aluminum',
    imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=200&auto=format&fit=crop&q=80',
    warehouseLocation: 'Zone A - Rack 01 - Bin A2',
    totalPhysicalStock: 85,
    reservedStock: 12,
    safetyStock: 8,
    availableStock: 65, // 85 - 12 - 8
    reorderPoint: 25,
    costPrice: 850.00,
    sellingPrice: 1899.00,
    autoSyncEnabled: true,
    lastBroadcastAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
    channelMappings: {
      tiktok: {
        isLinked: true,
        channelProductId: 'TTS_PRD_98492019',
        channelSkuId: 'TTS_SKU_10293849',
        channelStock: 65,
        price: 1955.00,
        lastSyncedAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopee: {
        isLinked: true,
        channelItemId: 'SP_ITEM_2938491028',
        channelModelId: 'SP_MOD_84920195',
        channelStock: 65,
        price: 1899.00,
        lastSyncedAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      lazada: {
        isLinked: true,
        channelSellerSku: 'LZ-AUR-ANC-SIL',
        channelItemId: 'LZ_ITEM_98372819',
        channelStock: 65,
        price: 1899.00,
        lastSyncedAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopify: {
        isLinked: true,
        channelVariantId: 'gid://shopify/ProductVariant/449201928472',
        channelProductId: 'gid://shopify/Product/8492019482',
        channelStock: 65,
        price: 499.00,
        lastSyncedAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      }
    },
    historyLog: [
      {
        id: 'hl-sil-1',
        timestamp: new Date(Date.now() - 12 * 60 * 60 * 1000).toISOString(),
        action: 'ORDER_RESERVATION',
        change: -1,
        resultingStock: 65,
        user: 'Shopee Webhook Listener',
        reason: 'Order #ORD-SP-240817U9 placed'
      }
    ]
  },
  {
    id: 'inv-03',
    masterSku: 'AUR-TWS-PRO-WHT',
    barcode: '8809281938036',
    productName: 'Aurora True Wireless Earbuds Pro',
    variantName: 'Ceramic White / Silver',
    imageUrl: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=200&auto=format&fit=crop&q=80',
    warehouseLocation: 'Zone A - Rack 02 - Bin C1',
    totalPhysicalStock: 240,
    reservedStock: 35,
    safetyStock: 15,
    availableStock: 190,
    reorderPoint: 50,
    costPrice: 280.00,
    sellingPrice: 699.00,
    autoSyncEnabled: true,
    lastBroadcastAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
    channelMappings: {
      tiktok: {
        isLinked: true,
        channelProductId: 'TTS_PRD_77492011',
        channelSkuId: 'TTS_SKU_33019284',
        channelStock: 190,
        price: 720.00,
        lastSyncedAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopee: {
        isLinked: true,
        channelItemId: 'SP_ITEM_1029384729',
        channelModelId: 'SP_MOD_33920194',
        channelStock: 190,
        price: 699.00,
        lastSyncedAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      lazada: {
        isLinked: true,
        channelSellerSku: 'LZ-AUR-TWS-WHT',
        channelItemId: 'LZ_ITEM_77281920',
        channelStock: 190,
        price: 699.00,
        lastSyncedAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopify: {
        isLinked: true,
        channelVariantId: 'gid://shopify/ProductVariant/5501928374',
        channelProductId: 'gid://shopify/Product/772819201',
        channelStock: 190,
        price: 179.00,
        lastSyncedAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      }
    },
    historyLog: [
      {
        id: 'hl-tws-1',
        timestamp: new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString(),
        action: 'ORDER_RESERVATION',
        change: -2,
        resultingStock: 190,
        user: 'Shopee Webhook Listener',
        reason: 'Order #ORD-SP-240817U9 (Qty: 2)'
      }
    ]
  },
  {
    id: 'inv-04',
    masterSku: 'AUR-DAC-V2-SLV',
    barcode: '8809281938043',
    productName: 'Aurora Studio Hi-Fi DAC & Amp V2',
    variantName: 'Brushed Silver / Dual ESS Sabre',
    imageUrl: 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=200&auto=format&fit=crop&q=80',
    warehouseLocation: 'Zone B - Rack 04 - Bin D3',
    totalPhysicalStock: 42,
    reservedStock: 8,
    safetyStock: 5,
    availableStock: 29, // 42 - 8 - 5 (Low Stock Alert)
    reorderPoint: 30,
    costPrice: 580.00,
    sellingPrice: 1299.00,
    autoSyncEnabled: true,
    lastBroadcastAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
    channelMappings: {
      tiktok: {
        isLinked: true,
        channelProductId: 'TTS_PRD_33910284',
        channelSkuId: 'TTS_SKU_88291039',
        channelStock: 29,
        price: 1339.00,
        lastSyncedAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopee: {
        isLinked: true,
        channelItemId: 'SP_ITEM_5592819482',
        channelModelId: 'SP_MOD_11928472',
        channelStock: 29,
        price: 1299.00,
        lastSyncedAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      lazada: {
        isLinked: true,
        channelSellerSku: 'LZ-AUR-DAC-SLV',
        channelItemId: 'LZ_ITEM_33910293',
        channelStock: 29,
        price: 1299.00,
        lastSyncedAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopify: {
        isLinked: true,
        channelVariantId: 'gid://shopify/ProductVariant/6619283749',
        channelProductId: 'gid://shopify/Product/991827364',
        channelStock: 29,
        price: 349.00,
        lastSyncedAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      }
    },
    historyLog: [
      {
        id: 'hl-dac-1',
        timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
        action: 'ORDER_RESERVATION',
        change: -1,
        resultingStock: 29,
        user: 'Lazada Open Platform',
        reason: 'Order #ORD-LZ-849201 reservation'
      }
    ]
  },
  {
    id: 'inv-05',
    masterSku: 'AUR-ST-MON-WAL',
    barcode: '8809281938050',
    productName: 'Aurora Studio Reference Monitors (Pair)',
    variantName: 'Solid Walnut / Kevlar Cones',
    imageUrl: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=200&auto=format&fit=crop&q=80',
    warehouseLocation: 'Zone C - Heavy Pallet Rack 02',
    totalPhysicalStock: 28,
    reservedStock: 4,
    safetyStock: 4,
    availableStock: 20,
    reorderPoint: 15,
    costPrice: 980.00,
    sellingPrice: 2499.00,
    autoSyncEnabled: true,
    lastBroadcastAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    channelMappings: {
      tiktok: {
        isLinked: false,
        channelProductId: '',
        channelSkuId: '',
        channelStock: 0,
        price: 0,
        lastSyncedAt: '',
        syncStatus: 'pending',
        errorMessage: 'Heavy item over 5kg: Courier shipping profile mapping required'
      },
      shopee: {
        isLinked: true,
        channelItemId: 'SP_ITEM_8839201948',
        channelModelId: 'SP_MOD_44920184',
        channelStock: 20,
        price: 2499.00,
        lastSyncedAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      lazada: {
        isLinked: true,
        channelSellerSku: 'LZ-AUR-MON-WAL',
        channelItemId: 'LZ_ITEM_55920194',
        channelStock: 20,
        price: 2499.00,
        lastSyncedAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      },
      shopify: {
        isLinked: true,
        channelVariantId: 'gid://shopify/ProductVariant/7729103849',
        channelProductId: 'gid://shopify/Product/110293847',
        channelStock: 20,
        price: 549.00,
        lastSyncedAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
        syncStatus: 'synced'
      }
    },
    historyLog: [
      {
        id: 'hl-mon-1',
        timestamp: new Date(Date.now() - 8 * 60 * 60 * 1000).toISOString(),
        action: 'ORDER_RESERVATION',
        change: -1,
        resultingStock: 20,
        user: 'Shopify Webhook Listener',
        reason: 'Order #ORD-SH-10492 reservation'
      }
    ]
  }
];

export const INITIAL_MARKETPLACE_SYNC_LOGS: MarketplaceSyncLog[] = [
  {
    id: 'log-01',
    timestamp: new Date(Date.now() - 2 * 60 * 1000).toISOString(),
    platform: 'tiktok',
    shopName: 'Aurora Audio Official Shop',
    eventType: 'order_status_update',
    status: 'success',
    referenceId: '578491029384729183',
    message: 'AWB Tracking #JT6082918302MY synced back to TikTok Open API successfully (HTTP 200)',
    payloadJson: JSON.stringify({
      order_id: '578491029384729183',
      tracking_number: 'JT6082918302MY',
      shipping_provider_id: 'JT_EXPRESS_MY',
      status: 'AWAITING_COLLECTION'
    }, null, 2),
    latencyMs: 142
  },
  {
    id: 'log-02',
    timestamp: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
    platform: 'shopee',
    shopName: 'Aurora Audio Flagship Mall',
    eventType: 'inventory_sync',
    status: 'success',
    referenceId: 'AUR-ANC-01-BLK',
    message: 'Master Stock 122 broadcasted to Shopee Item #2938491028 model #84920194',
    payloadJson: JSON.stringify({
      item_id: 2938491028,
      model_id: 84920194,
      stock: 122,
      price: 1899.00
    }, null, 2),
    latencyMs: 218
  },
  {
    id: 'log-03',
    timestamp: new Date(Date.now() - 9 * 60 * 1000).toISOString(),
    platform: 'shopify',
    shopName: 'Aurora Global Direct Store',
    eventType: 'order_created',
    status: 'success',
    referenceId: 'ORD-SH-10492',
    message: 'Webhook orders/create handled. Inventory auto-reserved and order routed to WMS queue',
    payloadJson: JSON.stringify({
      id: 5849201948,
      name: '#10492',
      currency: 'USD',
      total_price: '617.92',
      line_items: [{ sku: 'AUR-ST-MON-WAL', quantity: 1 }]
    }, null, 2),
    latencyMs: 89
  },
  {
    id: 'log-04',
    timestamp: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    platform: 'lazada',
    shopName: 'Aurora Audio LazMall Store',
    eventType: 'price_push',
    status: 'success',
    referenceId: 'AUR-DAC-V2-SLV',
    message: 'Price parity check confirmed (MYR 1,299.00)',
    payloadJson: JSON.stringify({
      seller_sku: 'LZ-AUR-DAC-SLV',
      price: 1299.00,
      special_price: null
    }, null, 2),
    latencyMs: 165
  },
  {
    id: 'log-05',
    timestamp: new Date(Date.now() - 32 * 60 * 1000).toISOString(),
    platform: 'tiktok',
    shopName: 'Aurora Audio Official Shop',
    eventType: 'token_refresh',
    status: 'success',
    referenceId: 'TTS_MY_748291038',
    message: 'OAuth 2.0 Access Token renewed automatically via TikTok Refresh Token API',
    payloadJson: JSON.stringify({
      grant_type: 'refresh_token',
      expires_in: 2592000,
      status: 'token_refreshed'
    }, null, 2),
    latencyMs: 310
  }
];
