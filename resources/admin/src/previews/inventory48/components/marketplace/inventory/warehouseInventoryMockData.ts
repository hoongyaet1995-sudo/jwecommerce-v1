import { StoreWithWarehouses, SkuWarehouseStockBalance, StorePlatformWarehouse, WarehouseLedgerEntry } from './warehouseInventoryTypes';

export const MOCK_STORE_WAREHOUSE_CONFIGS: StoreWithWarehouses[] = [
  // 1. 无仓店铺 (No Warehouse)
  {
    storeId: 'webstore',
    storeName: 'Aurora Audio Official Direct Storefront',
    storeNickname: 'JW Storefront (MY)',
    platform: 'webstore',
    warehouseMode: 'no_warehouse',
    warehouseSyncStatus: 'synced',
    isSkuMapped: true,
    warehouses: []
  },

  // 2. 单仓店铺 (Single Warehouse) - Lazada warehouse code
  {
    storeId: 'conn-lazada-01',
    storeName: 'Aurora Audio LazMall Store',
    storeNickname: 'Lazada LazMall MY',
    platform: 'lazada',
    warehouseMode: 'single_warehouse',
    warehouseSyncStatus: 'synced',
    isSkuMapped: true,
    warehouses: [
      {
        id: 'wh-lazada-main',
        storeId: 'conn-lazada-01',
        storeName: 'Aurora Audio LazMall Store',
        storeNickname: 'Lazada LazMall MY',
        platform: 'lazada',
        name: 'Shah Alam LazMall Hub',
        codeType: 'warehouse_code',
        codeValue: 'MY-WH-01',
        region: 'Central / Selangor',
        isDefault: true,
        addressSummary: 'Lot 12, Persiaran Perusahaan, Seksyen 23, 40300 Shah Alam',
        status: 'active'
      }
    ]
  },

  // 3. 多仓店铺 (8仓店铺 - 8 Warehouses) - Shopee location_id
  {
    storeId: 'conn-shopee-01',
    storeName: 'Aurora Audio Flagship Mall',
    storeNickname: 'Shopee Official MY',
    platform: 'shopee',
    warehouseMode: 'multi_warehouse',
    warehouseSyncStatus: 'synced',
    isSkuMapped: true,
    warehouses: [
      {
        id: 'wh-shopee-selangor',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Selangor Central Fulfillment Hub',
        codeType: 'location_id',
        codeValue: 'loc-10023812',
        region: 'Central (Selangor / KL)',
        isDefault: true,
        addressSummary: 'Bukit Jelutong Industrial Park, 40150 Shah Alam',
        status: 'active'
      },
      {
        id: 'wh-shopee-penang',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Penang North Distribution Center',
        codeType: 'location_id',
        codeValue: 'loc-88291044',
        region: 'Northern (Penang / Kedah)',
        addressSummary: 'Bayan Lepas Free Industrial Zone 4, 11900 Bayan Lepas',
        status: 'active'
      },
      {
        id: 'wh-shopee-johor',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Johor Bahru South Gateway Hub',
        codeType: 'location_id',
        codeValue: 'loc-55421098',
        region: 'Southern (Johor / SG Border)',
        addressSummary: 'Senai Airport City Industrial Park, 81400 Senai',
        status: 'active'
      },
      {
        id: 'wh-shopee-sabah',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Kota Kinabalu Sabah Hub',
        codeType: 'location_id',
        codeValue: 'loc-33190872',
        region: 'East Malaysia (Sabah)',
        addressSummary: 'KKIP Industrial Zone 1, 88450 Kota Kinabalu',
        status: 'active'
      },
      {
        id: 'wh-shopee-sarawak',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Kuching Sarawak Depot',
        codeType: 'location_id',
        codeValue: 'loc-44912033',
        region: 'East Malaysia (Sarawak)',
        addressSummary: 'Demak Laut Industrial Estate, 93050 Kuching',
        status: 'active'
      },
      {
        id: 'wh-shopee-ipoh',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Ipoh Buffer Warehouse',
        codeType: 'location_id',
        codeValue: 'loc-22819055',
        region: 'Northern (Perak)',
        addressSummary: 'Kinta Free Industrial Zone, 31400 Ipoh',
        status: 'active'
      },
      {
        id: 'wh-shopee-melaka',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Melaka Coastal Logistics Hub',
        codeType: 'location_id',
        codeValue: 'loc-77189021',
        region: 'Southern (Melaka / N. Sembilan)',
        addressSummary: 'Batu Berendam Industrial Area, 75350 Melaka',
        status: 'active'
      },
      {
        id: 'wh-shopee-kuantan',
        storeId: 'conn-shopee-01',
        storeName: 'Aurora Audio Flagship Mall',
        storeNickname: 'Shopee Official MY',
        platform: 'shopee',
        name: 'Kuantan East Coast Transit Hub',
        codeType: 'location_id',
        codeValue: 'loc-66230199',
        region: 'East Coast (Pahang / Terengganu)',
        addressSummary: 'Gebeng Industrial Estate Phase 2, 26080 Kuantan',
        status: 'active'
      }
    ]
  },

  // 4. 仓库资料待核实店铺 (Verification Pending) - API 超时/权限不足/资料不完整
  {
    storeId: 'conn-shopee-02',
    storeName: 'Aurora Audio Outlet Store',
    storeNickname: 'Shopee Secondary Outlet',
    platform: 'shopee',
    warehouseMode: 'multi_warehouse',
    warehouseSyncStatus: 'verification_pending',
    verificationPendingReason: 'Shopee Open API warehouse endpoint returned HTTP 403 (Multi-location warehouse scope permission missing). Re-authorization required.',
    isSkuMapped: true,
    warehouses: [
      {
        id: 'wh-shopee-outlet-unverified',
        storeId: 'conn-shopee-02',
        storeName: 'Aurora Audio Outlet Store',
        storeNickname: 'Shopee Secondary Outlet',
        platform: 'shopee',
        name: 'Klang Valley Outlet Center',
        codeType: 'location_id',
        codeValue: 'loc-unverified-09',
        region: 'Central',
        status: 'verification_pending'
      }
    ]
  },

  // 5. SKU 未映射店铺 (SKU Not Mapped)
  {
    storeId: 'conn-lazada-test-01',
    storeName: '[Official Test] Zappyworks Test Store',
    storeNickname: 'Lazada Test MY',
    platform: 'lazada',
    warehouseMode: 'single_warehouse',
    warehouseSyncStatus: 'synced',
    isSkuMapped: false,
    unmappedReason: 'External listing not yet linked to Master SKU. Map via SKU Mapping tab before managing inventory.',
    warehouses: [
      {
        id: 'wh-lazada-test-main',
        storeId: 'conn-lazada-test-01',
        storeName: '[Official Test] Zappyworks Test Store',
        storeNickname: 'Lazada Test MY',
        platform: 'lazada',
        name: 'Petaling Jaya Test Hub',
        codeType: 'warehouse_code',
        codeValue: 'TEST-WH-01',
        region: 'Central',
        status: 'active'
      }
    ]
  }
];

export const INITIAL_WAREHOUSE_STOCK_BALANCES: Record<string, Record<string, SkuWarehouseStockBalance>> = {
  // AUR-PINE-ANC-TT
  'AUR-PINE-ANC-TT': {
    // 1. 无仓店铺 (JW Storefront) - Direct virtual warehouse representation
    'wh-direct-webstore': {
      warehouseId: 'wh-direct-webstore',
      storeId: 'webstore',
      warehouseName: 'Storefront Direct Inventory',
      codeType: 'internal_bin',
      codeValue: 'DIRECT-WEB',
      isCounted: true,
      physicalStock: 8,
      reservedStock: 1,
      availableStock: 7,
      lastSyncAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 7
    },

    // 2. 单仓 (Lazada)
    'wh-lazada-main': {
      warehouseId: 'wh-lazada-main',
      storeId: 'conn-lazada-01',
      warehouseName: 'Shah Alam LazMall Hub',
      codeType: 'warehouse_code',
      codeValue: 'MY-WH-01',
      isCounted: true,
      physicalStock: 7,
      reservedStock: 1,
      availableStock: 6,
      lastSyncAt: new Date(Date.now() - 3 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 6
    },

    // 3. 多仓 8仓 (Shopee)
    'wh-shopee-selangor': {
      warehouseId: 'wh-shopee-selangor',
      storeId: 'conn-shopee-01',
      warehouseName: 'Selangor Central Fulfillment Hub',
      codeType: 'location_id',
      codeValue: 'loc-10023812',
      isCounted: true,
      physicalStock: 12,
      reservedStock: 2,
      availableStock: 10,
      lastSyncAt: new Date(Date.now() - 2 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 10
    },
    'wh-shopee-penang': {
      warehouseId: 'wh-shopee-penang',
      storeId: 'conn-shopee-01',
      warehouseName: 'Penang North Distribution Center',
      codeType: 'location_id',
      codeValue: 'loc-88291044',
      isCounted: true,
      physicalStock: 6,
      reservedStock: 1,
      availableStock: 5,
      lastSyncAt: new Date(Date.now() - 4 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 5
    },
    'wh-shopee-johor': {
      warehouseId: 'wh-shopee-johor',
      storeId: 'conn-shopee-01',
      warehouseName: 'Johor Bahru South Gateway Hub',
      codeType: 'location_id',
      codeValue: 'loc-55421098',
      isCounted: true,
      physicalStock: 5,
      reservedStock: 0,
      availableStock: 5,
      lastSyncAt: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 5
    },
    'wh-shopee-sabah': {
      warehouseId: 'wh-shopee-sabah',
      storeId: 'conn-shopee-01',
      warehouseName: 'Kota Kinabalu Sabah Hub',
      codeType: 'location_id',
      codeValue: 'loc-33190872',
      isCounted: true,
      physicalStock: 3,
      reservedStock: 0,
      availableStock: 3,
      lastSyncAt: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
      syncState: 'push_queued',
      lastPushedQuantity: 2
    },
    'wh-shopee-sarawak': {
      warehouseId: 'wh-shopee-sarawak',
      storeId: 'conn-shopee-01',
      warehouseName: 'Kuching Sarawak Depot',
      codeType: 'location_id',
      codeValue: 'loc-44912033',
      isCounted: true,
      physicalStock: 2,
      reservedStock: 0,
      availableStock: 2,
      lastSyncAt: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 2
    },
    'wh-shopee-ipoh': {
      warehouseId: 'wh-shopee-ipoh',
      storeId: 'conn-shopee-01',
      warehouseName: 'Ipoh Buffer Warehouse',
      codeType: 'location_id',
      codeValue: 'loc-22819055',
      isCounted: true,
      physicalStock: 4,
      reservedStock: 1,
      availableStock: 3,
      lastSyncAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
      syncState: 'push_failed',
      lastPushedQuantity: 0,
      failureReason: 'Shopee API response: Rate limit exceeded on update_stock endpoint. Local count saved.',
      canRetryPush: true
    },
    'wh-shopee-melaka': {
      warehouseId: 'wh-shopee-melaka',
      storeId: 'conn-shopee-01',
      warehouseName: 'Melaka Coastal Logistics Hub',
      codeType: 'location_id',
      codeValue: 'loc-77189021',
      isCounted: true,
      physicalStock: 2,
      reservedStock: 0,
      availableStock: 2,
      lastSyncAt: new Date(Date.now() - 18 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 2
    },
    'wh-shopee-kuantan': {
      warehouseId: 'wh-shopee-kuantan',
      storeId: 'conn-shopee-01',
      warehouseName: 'Kuantan East Coast Transit Hub',
      codeType: 'location_id',
      codeValue: 'loc-66230199',
      isCounted: true,
      physicalStock: 1,
      reservedStock: 0,
      availableStock: 1,
      lastSyncAt: new Date(Date.now() - 22 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 1
    },

    // 4. 仓库资料待核实 (Shopee Outlet)
    'wh-shopee-outlet-unverified': {
      warehouseId: 'wh-shopee-outlet-unverified',
      storeId: 'conn-shopee-02',
      warehouseName: 'Klang Valley Outlet Center',
      codeType: 'location_id',
      codeValue: 'loc-unverified-09',
      isCounted: false,
      physicalStock: null, // Unknown stock - NEVER display as 0!
      reservedStock: 0,
      availableStock: null,
      lastSyncAt: new Date(Date.now() - 48 * 60 * 60 * 1000).toISOString(),
      syncState: 'verification_pending',
      failureReason: 'Open API token expired. Warehouse data cannot be verified.'
    },

    // 5. SKU 未映射 (Lazada Test)
    'wh-lazada-test-main': {
      warehouseId: 'wh-lazada-test-main',
      storeId: 'conn-lazada-test-01',
      warehouseName: 'Petaling Jaya Test Hub',
      codeType: 'warehouse_code',
      codeValue: 'TEST-WH-01',
      isCounted: false,
      physicalStock: null,
      reservedStock: 0,
      availableStock: null,
      lastSyncAt: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
      syncState: 'unmapped',
      failureReason: 'SKU Not Mapped'
    }
  },

  // AUR-ANC-01-BLK
  'AUR-ANC-01-BLK': {
    'wh-direct-webstore': {
      warehouseId: 'wh-direct-webstore',
      storeId: 'webstore',
      warehouseName: 'Storefront Direct Inventory',
      codeType: 'internal_bin',
      codeValue: 'DIRECT-WEB',
      isCounted: true,
      physicalStock: 60,
      reservedStock: 8,
      availableStock: 52,
      lastSyncAt: new Date(Date.now() - 6 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 52
    },
    'wh-lazada-main': {
      warehouseId: 'wh-lazada-main',
      storeId: 'conn-lazada-01',
      warehouseName: 'Shah Alam LazMall Hub',
      codeType: 'warehouse_code',
      codeValue: 'MY-WH-01',
      isCounted: true,
      physicalStock: 45,
      reservedStock: 5,
      availableStock: 40,
      lastSyncAt: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 40
    },
    'wh-shopee-selangor': {
      warehouseId: 'wh-shopee-selangor',
      storeId: 'conn-shopee-01',
      warehouseName: 'Selangor Central Fulfillment Hub',
      codeType: 'location_id',
      codeValue: 'loc-10023812',
      isCounted: true,
      physicalStock: 25,
      reservedStock: 3,
      availableStock: 22,
      lastSyncAt: new Date(Date.now() - 7 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 22
    },
    'wh-shopee-penang': {
      warehouseId: 'wh-shopee-penang',
      storeId: 'conn-shopee-01',
      warehouseName: 'Penang North Distribution Center',
      codeType: 'location_id',
      codeValue: 'loc-88291044',
      isCounted: true,
      physicalStock: 20,
      reservedStock: 2,
      availableStock: 18,
      lastSyncAt: new Date(Date.now() - 7 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 18
    }
  },

  // JWSTORE1PS5-500GB
  'JWSTORE1PS5-500GB': {
    'wh-direct-webstore': {
      warehouseId: 'wh-direct-webstore',
      storeId: 'webstore',
      warehouseName: 'Storefront Direct Inventory',
      codeType: 'internal_bin',
      codeValue: 'DIRECT-WEB',
      isCounted: true,
      physicalStock: 22,
      reservedStock: 2,
      availableStock: 20,
      lastSyncAt: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 20
    },
    'wh-lazada-main': {
      warehouseId: 'wh-lazada-main',
      storeId: 'conn-lazada-01',
      warehouseName: 'Shah Alam LazMall Hub',
      codeType: 'warehouse_code',
      codeValue: 'MY-WH-01',
      isCounted: true,
      physicalStock: 0,
      reservedStock: 0,
      availableStock: 0,
      lastSyncAt: new Date(Date.now() - 14 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 0
    },
    'wh-shopee-selangor': {
      warehouseId: 'wh-shopee-selangor',
      storeId: 'conn-shopee-01',
      warehouseName: 'Selangor Central Fulfillment Hub',
      codeType: 'location_id',
      codeValue: 'loc-10023812',
      isCounted: true,
      physicalStock: 0,
      reservedStock: 0,
      availableStock: 0,
      lastSyncAt: new Date(Date.now() - 11 * 60 * 1000).toISOString(),
      syncState: 'synced',
      lastPushedQuantity: 0
    }
  }
};

export const INITIAL_WAREHOUSE_LEDGER: WarehouseLedgerEntry[] = [
  {
    id: 'wled-101',
    timestamp: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
    masterSku: 'AUR-PINE-ANC-TT',
    storeId: 'conn-shopee-01',
    storeName: 'Aurora Audio Flagship Mall',
    warehouseId: 'wh-shopee-selangor',
    warehouseName: 'Selangor Central Fulfillment Hub',
    codeValue: 'loc-10023812',
    action: 'order_reserved',
    quantityChange: -1,
    resultingPhysical: 12,
    resultingAvailable: 10,
    operator: 'Shopee Webhook (Order #260923SHP8821)',
    reason: 'Customer placed order allocated to Selangor Central Hub',
    referenceId: '260923SHP8821',
    platformSyncStatus: 'confirmed'
  },
  {
    id: 'wled-102',
    timestamp: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
    masterSku: 'AUR-PINE-ANC-TT',
    storeId: 'conn-shopee-01',
    storeName: 'Aurora Audio Flagship Mall',
    warehouseId: 'wh-shopee-penang',
    warehouseName: 'Penang North Distribution Center',
    codeValue: 'loc-88291044',
    action: 'intake',
    quantityChange: 4,
    resultingPhysical: 6,
    resultingAvailable: 5,
    operator: 'Alexander (Admin)',
    reason: 'Supplier PO Delivery Verification (PO-2026-092)',
    referenceId: 'PO-2026-092',
    platformSyncStatus: 'confirmed'
  },
  {
    id: 'wled-103',
    timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
    masterSku: 'AUR-PINE-ANC-TT',
    storeId: 'conn-lazada-01',
    storeName: 'Aurora Audio LazMall Store',
    warehouseId: 'wh-lazada-main',
    warehouseName: 'Shah Alam LazMall Hub',
    codeValue: 'MY-WH-01',
    action: 'audit_adjustment',
    quantityChange: -1,
    resultingPhysical: 7,
    resultingAvailable: 6,
    operator: 'Marcus (Ops Manager)',
    reason: 'Cycle count variance adjustment - damaged carton',
    referenceId: 'AUD-LZD-041',
    platformSyncStatus: 'confirmed'
  }
];
