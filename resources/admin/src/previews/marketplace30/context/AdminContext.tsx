import React, { useState } from 'react';
import {PreviewContext} from './ContextBridge';
export {useAdmin} from './ContextBridge';
import { AdminUser, NavSection, Category, Product, ProductVariant, MarketplacePlatform, MarketplaceConnection, MarketplaceOrder, MarketplaceInventoryItem, MarketplaceSyncLog, MarketplaceStoreProduct } from '../types';
import { INITIAL_ADMIN_USER, INITIAL_CATEGORIES, INITIAL_PRODUCTS } from '../data/mockData';


import { INITIAL_MARKETPLACE_CONNECTIONS, INITIAL_MARKETPLACE_ORDERS, INITIAL_MARKETPLACE_INVENTORY, INITIAL_MARKETPLACE_SYNC_LOGS, INITIAL_MARKETPLACE_STORE_PRODUCTS } from '../data/marketplaceMockData';











const STORAGE_KEYS = {
  THEME: 'aurora_admin_theme',
  USER: 'aurora_admin_user',
  HEADER_MENUS: 'aurora_admin_header_menus',
  FOOTER_MENUS: 'aurora_admin_footer_menus',
  CATEGORIES: 'aurora_admin_categories',
  COLLECTIONS: 'aurora_admin_collections',
  PRODUCTS: 'aurora_admin_products',
  PAGES: 'aurora_admin_pages',
  CUSTOMERS: 'aurora_admin_customers',
  ORDERS: 'aurora_admin_orders',
  STAFF_USERS: 'aurora_admin_staff_users',
  SHOP_SETTINGS: 'aurora_admin_shop_settings',
  MARKETPLACE_CONNECTIONS: 'aurora_admin_mp_connections',
  MARKETPLACE_ORDERS: 'aurora_admin_mp_orders',
  MARKETPLACE_INVENTORY: 'aurora_admin_mp_inventory',
  MARKETPLACE_STORE_PRODUCTS: 'aurora_admin_mp_store_products',
  MARKETPLACE_SYNC_LOGS: 'aurora_admin_mp_sync_logs',
  COURIER_CONFIGS: 'aurora_admin_courier_configs',
  SMART_ROUTING_RULES: 'aurora_admin_smart_routing_rules',
  SHIPMENT_PARCELS: 'aurora_admin_shipment_parcels',
  PICKUP_REQUESTS: 'aurora_admin_pickup_requests',
  BILLING_RECONCILIATIONS: 'aurora_admin_billing_reconciliations',
  SHIPPING_RATES: 'aurora_admin_shipping_rates',
  PAYMENT_GATEWAYS: 'aurora_admin_payment_gateways',
  BANK_TRANSFER: 'aurora_admin_bank_transfer',
  COD_CONFIG: 'aurora_admin_cod_config',
  GLOBAL_PAYMENT: 'aurora_admin_global_payment',
  CUSTOM_ORDER_STATUSES: 'aurora_admin_order_statuses',
  NOTIFICATION_TEMPLATES: 'aurora_admin_notification_templates',
  COUNTRIES_SETTING: 'aurora_admin_countries_setting',
  ZONES_SETTING: 'aurora_admin_zones_setting',
  CURRENCIES_SETTING: 'aurora_admin_currencies_setting',
  ORDER_TOTAL_MODULES: 'aurora_admin_order_total_modules',
  GEO_TAX_ZONES: 'aurora_admin_geo_tax_zones',
  TAX_CLASSES: 'aurora_admin_tax_classes',
  TAX_RATES: 'aurora_admin_tax_rates',
  GLOBAL_TAX_ENGINE: 'aurora_admin_global_tax_engine',
  SUPPORT_CONVERSATIONS: 'aurora_admin_support_conversations',
  COPILOT_MESSAGES: 'aurora_admin_copilot_messages',
  AI_AUTO_REPLY_CONFIG: 'aurora_admin_ai_auto_reply_config',
  REVIEWS: 'aurora_admin_product_reviews',
  STOREFRONT_HEADER_CONFIG: 'aurora_admin_storefront_header_config',
};
interface Toast {id:string;type:'success'|'info'|'warning'|'error';title:string;message:string;}
const previewStorage = { getItem: (_key: string): string | null => null, setItem: (_key: string, _value: string) => {}, removeItem: (_key: string) => {} };
export function usePreviewModel(){
const [currentSection, setCurrentSection] = useState<NavSection>('dashboard');
const [user, setUser] = useState<AdminUser | null>(INITIAL_ADMIN_USER);
const [toasts, setToasts] = useState<Toast[]>([]);
const [categories, setCategories] = useState<Category[]>(INITIAL_CATEGORIES);
const [products, setProducts] = useState<Product[]>(INITIAL_PRODUCTS);
const [connections, setConnections] = useState<MarketplaceConnection[]>(INITIAL_MARKETPLACE_CONNECTIONS);
const [marketplaceOrders, setMarketplaceOrders] = useState<MarketplaceOrder[]>(INITIAL_MARKETPLACE_ORDERS);
const [marketplaceInventory, setMarketplaceInventory] = useState<MarketplaceInventoryItem[]>(INITIAL_MARKETPLACE_INVENTORY);
const [marketplaceStoreProducts, setMarketplaceStoreProducts] = useState<MarketplaceStoreProduct[]>(() => {
    try {
      const saved = previewStorage.getItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS);
      if (saved) return JSON.parse(saved);
    } catch {}
    return INITIAL_MARKETPLACE_STORE_PRODUCTS;
  });
const [syncLogs, setSyncLogs] = useState<MarketplaceSyncLog[]>(INITIAL_MARKETPLACE_SYNC_LOGS);
const [selectedMarketplaceOrderId, setSelectedMarketplaceOrderId] = useState<string | null>(null);
const addToast = (toast: Omit<Toast, 'id'>) => {
    const id = Math.random().toString(36).substring(2, 9);
    setToasts(prev => [...prev, { ...toast, id }]);
    setTimeout(() => {
      removeToast(id);
    }, 4000);
  };
const removeToast = (id: string) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  };
const addSyncLog = (log: Omit<MarketplaceSyncLog, 'id' | 'timestamp'>) => {
    const newLog: MarketplaceSyncLog = {
      id: `log-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      timestamp: new Date().toISOString(),
      ...log
    };
    setSyncLogs(prev => {
      const updated = [newLog, ...prev.slice(0, 99)]; // Keep latest 100 logs
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_SYNC_LOGS, JSON.stringify(updated));
      return updated;
    });
  };
const retrySyncLog = (logId: string) => {
    const target = syncLogs.find(l => l.id === logId);
    if (!target) return;
    addToast({ type: 'info', title: 'Retrying Sync Event', message: `Resending ${target.eventType} to ${target.platform.toUpperCase()} API...` });
    setTimeout(() => {
      setSyncLogs(prev => {
        const updated = prev.map(l => l.id === logId ? { ...l, status: 'success' as const, message: `[Retry Success] Re-processed event: ${l.eventType}`, timestamp: new Date().toISOString() } : l);
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_SYNC_LOGS, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Sync Successful', message: `API payload re-transmitted to ${target.shopName} successfully.` });
    }, 600);
  };
const clearSyncLogs = () => {
    setSyncLogs([]);
    previewStorage.removeItem(STORAGE_KEYS.MARKETPLACE_SYNC_LOGS);
    addToast({ type: 'info', title: 'Logs Cleared', message: 'Marketplace webhook and sync activity logs have been reset.' });
  };
const saveConnection = (conn: MarketplaceConnection) => {
    setConnections(prev => {
      const exists = prev.some(c => c.id === conn.id);
      const updated = exists ? prev.map(c => c.id === conn.id ? conn : c) : [...prev, conn];
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Store Connection Saved',
      message: `Successfully configured ${conn.platform.toUpperCase()} store "${conn.shopName}".`
    });
    addSyncLog({
      platform: conn.platform,
      shopName: conn.shopName,
      eventType: 'token_refresh',
      status: 'success',
      referenceId: conn.shopId,
      message: `Open API credentials configured and token authorized for ${conn.region} region.`,
      payloadJson: JSON.stringify({ shopId: conn.shopId, status: conn.status, region: conn.region }, null, 2),
      latencyMs: 120
    });
  };
const deleteConnection = (id: string) => {
    setConnections(prev => {
      const target = prev.find(c => c.id === id);
      const updated = prev.filter(c => c.id !== id);
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      addToast({
        type: 'warning',
        title: 'Connection Removed',
        message: `Permanently removed store connection "${target?.shopName || id}".`
      });
      return updated;
    });
  };
const disconnectConnection = (id: string, reason?: string) => {
    setConnections(prev => {
      const target = prev.find(c => c.id === id);
      const updated = prev.map(c => {
        if (c.id === id) {
          return {
            ...c,
            status: 'disconnected' as const,
            disconnectedAt: new Date().toISOString(),
            disconnectReason: reason || 'Merchant requested store disconnect',
            autoSyncStock: false,
            autoPullOrders: false
          };
        }
        return c;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      addToast({
        type: 'warning',
        title: 'Store Disconnected',
        message: `Real-time synchronization stopped for "${target?.shopName || id}". All historical orders and SKU mappings are safely preserved.`
      });
      return updated;
    });

    const target = connections.find(c => c.id === id);
    addSyncLog({
      platform: target?.platform || 'shopee',
      shopName: target?.shopName || id,
      eventType: 'store_disconnect',
      status: 'warning',
      message: `Store ${id} disconnected. Automated stock sync and order ingestion halted; historical data preserved.`,
      referenceId: id,
      payloadJson: JSON.stringify({ connectionId: id, action: 'disconnect' }),
      latencyMs: 95
    });
  };
const reconnectConnection = (id: string) => {
    let reconnectedPlatform: MarketplacePlatform = 'shopee';
    let reconnectedShopName = id;

    setConnections(prev => {
      const target = prev.find(c => c.id === id);
      if (target) {
        reconnectedPlatform = target.platform;
        reconnectedShopName = target.shopName;
      }
      const updated = prev.map(c => {
        if (c.id === id) {
          return {
            ...c,
            status: 'connected' as const,
            tokenExpiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
            lastSyncAt: new Date().toISOString(),
            autoSyncStock: true,
            autoPullOrders: true,
            disconnectedAt: undefined,
            disconnectReason: undefined
          };
        }
        return c;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      addToast({
        type: 'success',
        title: 'Store Reconnected & Active',
        message: `Access authorization refreshed for "${target?.shopName || id}". Automatic sync resumed.`
      });
      return updated;
    });

    addSyncLog({
      platform: reconnectedPlatform,
      shopName: reconnectedShopName,
      eventType: 'store_connect',
      status: 'success',
      message: `Store ${id} re-authorized and connection restored. Active order & stock sync resumed.`,
      referenceId: id,
      payloadJson: JSON.stringify({ connectionId: id, action: 'reconnect' }),
      latencyMs: 140
    });
  };
const toggleConnectionSync = (id: string, field: 'autoSyncStock' | 'autoPullOrders') => {
    setConnections(prev => {
      const updated = prev.map(c => {
        if (c.id === id) {
          const nextVal = !c[field];
          return { ...c, [field]: nextVal };
        }
        return c;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      const target = updated.find(c => c.id === id);
      if (target) {
        addToast({
          type: 'info',
          title: 'Sync Policy Updated',
          message: `${target.shopName} ${field === 'autoSyncStock' ? 'Stock Sync' : 'Order Pull'} is now ${target[field] ? 'Active' : 'Paused'}.`
        });
      }
      return updated;
    });
  };
const refreshToken = (id: string) => {
    const conn = connections.find(c => c.id === id);
    if (!conn) return;
    addToast({ type: 'info', title: 'Refreshing OAuth Token', message: `Requesting new access token from ${conn.platform.toUpperCase()} auth server...` });
    setTimeout(() => {
      const newExpiry = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString();
      const updatedConn: MarketplaceConnection = {
        ...conn,
        status: 'connected',
        tokenExpiresAt: newExpiry,
        lastSyncAt: new Date().toISOString()
      };
      saveConnection(updatedConn);
      addToast({ type: 'success', title: 'Token Renewed', message: `Token valid for next 30 days until ${new Date(newExpiry).toLocaleDateString()}.` });
    }, 700);
  };
const syncAllMarketplaces = () => {
    addToast({ type: 'info', title: 'Syncing Omnichannel Stream', message: 'Pulling new orders & updating tracking writebacks from TikTok, Shopee, Lazada & Shopify...' });
    setTimeout(() => {
      setConnections(prev => {
        const updated = prev.map(c => ({ ...c, lastSyncAt: new Date().toISOString() }));
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Sync Complete', message: 'All connected marketplace channels are fully synchronized.' });
      addSyncLog({
        platform: 'shopee',
        shopName: 'All Channels',
        eventType: 'inventory_sync',
        status: 'success',
        referenceId: 'SYNC_ALL_CHANNELS',
        message: 'Omnichannel polling cycle executed without errors.',
        payloadJson: JSON.stringify({ syncedChannels: connections.map(c => c.shopName), timestamp: new Date().toISOString() }, null, 2),
        latencyMs: 340
      });
    }, 800);
  };
const generateAirwayBill = (orderId: string, courierName?: string) => {
    setMarketplaceOrders(prev => {
      const updated = prev.map(o => {
        if (o.id === orderId) {
          const defaultCourier = courierName || o.trackingCarrier || (o.channel === 'tiktok' ? 'J&T Express (TikTok Shipping)' : o.channel === 'shopee' ? 'Shopee Supported Logistics (SPX)' : o.channel === 'lazada' ? 'Lazada Logistics Express (LEX)' : 'DHL Express');
          const trackingNum = o.trackingNumber || `${o.channel.substring(0, 2).toUpperCase()}${Math.floor(1000000000 + Math.random() * 9000000000)}MY`;
          const updatedOrder: MarketplaceOrder = {
            ...o,
            airwayBillGenerated: true,
            airwayBillUrl: `https://labels.auroracommerce.io/awb/${o.channelOrderId || o.orderNumber}.pdf`,
            trackingCarrier: defaultCourier,
            trackingNumber: trackingNum,
            channelStatus: 'READY_TO_SHIP',
            fulfillmentStatus: o.fulfillmentStatus === 'unfulfilled' ? 'processing' : o.fulfillmentStatus,
            timeline: [
              {
                id: `evt-${Date.now()}`,
                timestamp: new Date().toISOString(),
                type: 'fulfillment_updated',
                title: `AWB Generated (${defaultCourier})`,
                description: `Airway bill generated with Tracking #${trackingNum}. Package ready for courier handover.`
              },
              ...o.timeline
            ]
          };
          return updatedOrder;
        }
        return o;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });

    const target = marketplaceOrders.find(o => o.id === orderId);
    if (target) {
      addToast({ type: 'success', title: 'Airway Bill Generated', message: `AWB ready for #${target.orderNumber}. Courier tracking registered.` });
      addSyncLog({
        platform: target.channel,
        shopName: target.channelStoreName,
        eventType: 'awb_generated',
        status: 'success',
        referenceId: target.channelOrderId,
        message: `Airway bill & courier dispatch registered with ${target.channel.toUpperCase()} Open API logistics service.`,
        payloadJson: JSON.stringify({ orderId: target.channelOrderId, carrier: courierName || target.trackingCarrier }, null, 2),
        latencyMs: 190
      });
    }
  };
const markOrderPacked = (orderId: string, packedBy: string) => {
    setMarketplaceOrders(prev => {
      const updated = prev.map(o => {
        if (o.id === orderId) {
          return {
            ...o,
            channelStatus: 'READY_TO_SHIP',
            packedBy: packedBy || 'Warehouse Staff',
            scannedItemsCount: o.items.reduce((sum, item) => sum + item.quantity, 0),
            fulfillmentStatus: 'processing' as const,
            timeline: [
              {
                id: `evt-${Date.now()}`,
                timestamp: new Date().toISOString(),
                type: 'fulfillment_updated',
                title: 'Items Picked & Packed',
                description: `Order verified and packaged by ${packedBy || 'Staff'}. Ready for courier pickup.`
              },
              ...o.timeline
            ]
          };
        }
        return o;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'Order Packed', message: `Order #${orderId} marked as packed and verified.` });
  };
const markOrderShipped = (orderId: string, trackingNumber: string, carrier: string) => {
    setMarketplaceOrders(prev => {
      const updated = prev.map(o => {
        if (o.id === orderId) {
          const updatedOrder: MarketplaceOrder = {
            ...o,
            channelStatus: 'IN_TRANSIT',
            fulfillmentStatus: 'shipped',
            trackingNumber: trackingNumber || o.trackingNumber,
            trackingCarrier: carrier || o.trackingCarrier,
            timeline: [
              {
                id: `evt-${Date.now()}`,
                timestamp: new Date().toISOString(),
                type: 'fulfillment_updated',
                title: 'Handed Over to Courier',
                description: `Package collected by ${carrier || o.trackingCarrier || 'Courier'} [Tracking #${trackingNumber || o.trackingNumber}]. Auto-written back to marketplace API.`
              },
              ...o.timeline
            ]
          };
          return updatedOrder;
        }
        return o;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });

    const target = marketplaceOrders.find(o => o.id === orderId);
    if (target) {
      addToast({ type: 'success', title: 'Order Dispatched', message: `Order #${target.orderNumber} dispatched. Tracking synced to ${target.channel.toUpperCase()}.` });
      addSyncLog({
        platform: target.channel,
        shopName: target.channelStoreName,
        eventType: 'order_status_update',
        status: 'success',
        referenceId: target.channelOrderId,
        message: `Courier handover confirmed. Status updated to SHIPPED / IN_TRANSIT on ${target.channel.toUpperCase()}.`,
        payloadJson: JSON.stringify({ orderId: target.channelOrderId, trackingNumber, carrier }, null, 2),
        latencyMs: 145
      });
    }
  };
const batchPrintAWB = (orderIds: string[]) => {
    orderIds.forEach(id => generateAirwayBill(id));
    addToast({ type: 'success', title: 'Batch AWB Generation', message: `Successfully generated ${orderIds.length} printable shipping label(s).` });
  };
const batchPackOrders = (orderIds: string[]) => {
    orderIds.forEach(id => markOrderPacked(id, 'Batch Scanner'));
    addToast({ type: 'success', title: 'Batch Orders Packed', message: `Marked ${orderIds.length} order(s) as packed and ready for dispatch.` });
  };
const saveInventoryItem = (item: MarketplaceInventoryItem) => {
    setMarketplaceInventory(prev => {
      const exists = prev.some(i => i.id === item.id);
      const updated = exists ? prev.map(i => i.id === item.id ? item : i) : [...prev, item];
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'SKU Inventory Saved', message: `Master SKU ${item.masterSku} updated.` });
  };
const adjustStock = (idOrSku: string, change: number, reason: string, user: string = 'Admin') => {
    setMarketplaceInventory(prev => {
      const updated = prev.map(item => {
        if (item.id === idOrSku || item.masterSku === idOrSku) {
          const newPhysical = Math.max(0, item.totalPhysicalStock + change);
          const newAvailable = Math.max(0, newPhysical - item.reservedStock - item.safetyStock);
          const newLog = {
            id: `hl-${Date.now()}`,
            timestamp: new Date().toISOString(),
            action: change >= 0 ? 'STOCK_INBOUND' : 'STOCK_ADJUSTMENT',
            change,
            resultingStock: newAvailable,
            user,
            reason: reason || 'Manual Stock Adjustment'
          };
          const updatedChannels = { ...item.channelMappings };
          // Update channel stock
          (['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).forEach(plat => {
            if (plat !== 'direct' && updatedChannels[plat]?.isLinked) {
              updatedChannels[plat] = {
                ...updatedChannels[plat]!,
                channelStock: newAvailable,
                lastSyncedAt: new Date().toISOString(),
                syncStatus: 'synced'
              };
            }
          });

          return {
            ...item,
            totalPhysicalStock: newPhysical,
            availableStock: newAvailable,
            channelMappings: updatedChannels,
            historyLog: [newLog, ...item.historyLog]
          };
        }
        return item;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });

    addToast({
      type: change >= 0 ? 'success' : 'warning',
      title: 'Warehouse Stock Adjusted',
      message: `${idOrSku}: ${change >= 0 ? `+${change}` : change} units applied (${reason}).`
    });

    addSyncLog({
      platform: 'shopee',
      shopName: 'All Channels',
      eventType: 'inventory_sync',
      status: 'success',
      referenceId: idOrSku,
      message: `Master inventory delta (${change >= 0 ? `+${change}` : change}) broadcasted to connected marketplace channels.`,
      payloadJson: JSON.stringify({ sku: idOrSku, change, reason, user }, null, 2),
      latencyMs: 180
    });
  };
const broadcastStockToChannels = (sku: string) => {
    const item = marketplaceInventory.find(i => i.masterSku === sku || i.id === sku);
    if (!item) return;

    addToast({ type: 'info', title: 'Broadcasting Stock', message: `Syncing ${item.availableStock} units of ${item.masterSku} to TikTok, Shopee, Lazada & Shopify...` });
    setTimeout(() => {
      setMarketplaceInventory(prev => {
        const updated = prev.map(i => {
          if (i.masterSku === sku || i.id === sku) {
            const updatedChannels = { ...i.channelMappings };
            (['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).forEach(plat => {
              if (plat !== 'direct' && updatedChannels[plat]?.isLinked) {
                updatedChannels[plat] = {
                  ...updatedChannels[plat]!,
                  channelStock: i.availableStock,
                  lastSyncedAt: new Date().toISOString(),
                  syncStatus: 'synced'
                };
              }
            });
            return {
              ...i,
              lastBroadcastAt: new Date().toISOString(),
              channelMappings: updatedChannels
            };
          }
          return i;
        });
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Stock Broadcast Complete', message: `All marketplace channels updated with ${item.availableStock} units for ${item.masterSku}.` });
      addSyncLog({
        platform: 'tiktok',
        shopName: 'All Channels',
        eventType: 'inventory_sync',
        status: 'success',
        referenceId: item.masterSku,
        message: `Inventory balance broadcasted to all channels successfully.`,
        payloadJson: JSON.stringify({ masterSku: item.masterSku, availableStock: item.availableStock }, null, 2),
        latencyMs: 220
      });
    }, 700);
  };
const broadcastAllStockToChannels = () => {
    addToast({ type: 'info', title: 'Mass Inventory Broadcast', message: `Broadcasting warehouse ATP stocks for ${marketplaceInventory.length} Master SKUs across all connected marketplace shops...` });
    setTimeout(() => {
      setMarketplaceInventory(prev => {
        const updated = prev.map(i => {
          const updatedChannels = { ...i.channelMappings };
          (['tiktok', 'shopee', 'lazada', 'shopify'] as MarketplacePlatform[]).forEach(plat => {
            if (plat !== 'direct' && updatedChannels[plat]?.isLinked) {
              updatedChannels[plat] = {
                ...updatedChannels[plat]!,
                channelStock: i.availableStock,
                lastSyncedAt: new Date().toISOString(),
                syncStatus: 'synced'
              };
            }
          });
          return {
            ...i,
            lastBroadcastAt: new Date().toISOString(),
            channelMappings: updatedChannels
          };
        });
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
        return updated;
      });
      addToast({ type: 'success', title: 'Mass Broadcast Complete', message: `Successfully synchronized inventory across all 4 marketplaces.` });
      addSyncLog({
        platform: 'shopee',
        shopName: 'Omnichannel Broadcast',
        eventType: 'inventory_sync',
        status: 'success',
        referenceId: 'MASS_BROADCAST',
        message: `Bulk inventory batch sync completed for ${marketplaceInventory.length} SKUs.`,
        payloadJson: JSON.stringify({ skuCount: marketplaceInventory.length, timestamp: new Date().toISOString() }, null, 2),
        latencyMs: 480
      });
    }, 900);
  };
const linkChannelSku = (inventoryId: string, platform: MarketplacePlatform, mapping: import('../types').ChannelSkuMapping) => {
    if (platform === 'direct') return;
    setMarketplaceInventory(prev => {
      const updated = prev.map(item => {
        if (item.id === inventoryId) {
          return {
            ...item,
            channelMappings: {
              ...item.channelMappings,
              [platform]: mapping
            }
          };
        }
        return item;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'success', title: 'Channel SKU Mapped', message: `Linked ${platform.toUpperCase()} SKU mapping for item.` });
  };
const unlinkChannelSku = (inventoryId: string, platform: MarketplacePlatform) => {
    if (platform === 'direct') return;
    setMarketplaceInventory(prev => {
      const updated = prev.map(item => {
        if (item.id === inventoryId) {
          const currentMap = item.channelMappings[platform];
          if (!currentMap) return item;
          return {
            ...item,
            channelMappings: {
              ...item.channelMappings,
              [platform]: {
                ...currentMap,
                isLinked: false
              }
            }
          };
        }
        return item;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });
    addToast({ type: 'warning', title: 'Channel Unlinked', message: `Removed ${platform.toUpperCase()} SKU link.` });
  };
const mapStoreVariantToJwSku = (
    storeProductId: string,
    storeVariantId: string,
    jwProductId: string,
    jwVariantId: string
  ) => {
    const targetProduct = products.find(p => p.id === jwProductId);
    const targetVariant = targetProduct?.variants?.find(v => v.id === jwVariantId);

    if (!targetProduct || !targetVariant) {
      addToast({
        type: 'error',
        title: 'Mapping Failed',
        message: 'Could not find selected JW master product or variant. Please try again.'
      });
      return;
    }

    const currentStoreProd = marketplaceStoreProducts.find(p => p.id === storeProductId);
    if (!currentStoreProd) return;

    // Check SAME-STORE uniqueness constraint:
    // Within the SAME store, a JW variant can only be used once.
    // Across DIFFERENT stores, reuse is allowed.
    let conflictingItem: { productTitle: string; variantTitle: string; channelSku: string } | null = null;

    for (const prod of marketplaceStoreProducts) {
      if (prod.connectionId === currentStoreProd.connectionId) {
        for (const variant of prod.variants) {
          if (
            variant.mappedJwVariantId === jwVariantId &&
            variant.mappingStatus === 'mapped' &&
            !(prod.id === storeProductId && variant.id === storeVariantId)
          ) {
            conflictingItem = {
              productTitle: prod.title,
              variantTitle: variant.title,
              channelSku: variant.channelSellerSku
            };
            break;
          }
        }
      }
      if (conflictingItem) break;
    }

    if (conflictingItem) {
      addToast({
        type: 'error',
        title: 'Same-Store Mapping Conflict',
        message: `JW Variant (${targetVariant.sku} · ${targetVariant.title}) is already linked to "${conflictingItem.productTitle} (${conflictingItem.variantTitle})" in this store. Each JW variant can only be linked once per store.`
      });
      return;
    }

    setMarketplaceStoreProducts(prev => {
      const updated = prev.map(prod => {
        if (prod.id !== storeProductId) return prod;
        const updatedVariants = prod.variants.map(v => {
          if (v.id !== storeVariantId) return v;
          return {
            ...v,
            mappingStatus: 'mapped' as const,
            mappedJwProductId: targetProduct.id,
            mappedJwVariantId: targetVariant.id,
            mappedJwSku: targetVariant.sku,
            mappedJwProductName: targetProduct.title,
            mappedJwVariantTitle: targetVariant.title,
            mappedJwVariantImage: targetVariant.image || targetProduct.images?.[0],
            mappedJwStock: targetVariant.inventoryQuantity,
            mappedJwPrice: targetVariant.price,
            mappedAt: new Date().toISOString(),
            mappedBy: user?.name || 'Staff User',
            conflictReason: undefined,
            suggestedMatch: undefined
          };
        });
        return { ...prod, variants: updatedVariants, updatedAt: new Date().toISOString() };
      });
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(updated));
      } catch {}
      return updated;
    });

    addToast({
      type: 'success',
      title: 'Variant Mapping Linked',
      message: `Successfully mapped channel variant to JW Master SKU: ${targetVariant.sku} (${targetVariant.title})`
    });
  };
const unmapStoreVariant = (storeProductId: string, storeVariantId: string) => {
    setMarketplaceStoreProducts(prev => {
      const updated = prev.map(prod => {
        if (prod.id !== storeProductId) return prod;
        const updatedVariants = prod.variants.map(v => {
          if (v.id !== storeVariantId) return v;
          return {
            ...v,
            mappingStatus: 'unmapped' as const,
            mappedJwProductId: undefined,
            mappedJwVariantId: undefined,
            mappedJwSku: undefined,
            mappedJwProductName: undefined,
            mappedJwVariantTitle: undefined,
            mappedJwVariantImage: undefined,
            mappedJwStock: undefined,
            mappedJwPrice: undefined,
            mappedAt: undefined,
            mappedBy: undefined,
            conflictReason: undefined
          };
        });
        return { ...prod, variants: updatedVariants, updatedAt: new Date().toISOString() };
      });
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(updated));
      } catch {}
      return updated;
    });

    addToast({
      type: 'info',
      title: 'Variant Mapping Unlinked',
      message: 'Channel variant mapping has been removed.'
    });
  };
const batchAcceptSuggestedMappings = (storeProductId?: string) => {
    let acceptedCount = 0;
    setMarketplaceStoreProducts(prev => {
      const updated = prev.map(prod => {
        if (storeProductId && prod.id !== storeProductId) return prod;
        const updatedVariants = prod.variants.map(v => {
          if (v.mappingStatus === 'unmapped' && v.suggestedMatch) {
            const suggestion = v.suggestedMatch;
            acceptedCount++;
            return {
              ...v,
              mappingStatus: 'mapped' as const,
              mappedJwProductId: suggestion.productId,
              mappedJwVariantId: suggestion.variantId,
              mappedJwSku: suggestion.sku,
              mappedJwProductName: suggestion.productName,
              mappedJwVariantTitle: suggestion.variantTitle,
              mappedJwVariantImage: suggestion.imageUrl,
              mappedJwStock: suggestion.stock,
              mappedJwPrice: suggestion.price,
              mappedAt: new Date().toISOString(),
              mappedBy: user?.name ? `${user.name} (Auto Accepted)` : 'Staff (Auto Accepted)',
              suggestedMatch: undefined
            };
          }
          return v;
        });
        return { ...prod, variants: updatedVariants, updatedAt: new Date().toISOString() };
      });
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(updated));
      } catch {}
      return updated;
    });

    if (acceptedCount > 0) {
      addToast({
        type: 'success',
        title: 'Batch Suggestions Accepted',
        message: `Successfully linked ${acceptedCount} variants based on high-confidence recommendations.`
      });
    } else {
      addToast({
        type: 'info',
        title: 'No Pending Suggestions',
        message: 'No unmapped variants with recommended matches found in the selected scope.'
      });
    }
  };
const refreshStoreProductsFromChannel = async (connectionId?: string) => {
    await new Promise(resolve => setTimeout(resolve, 700));
    const nowIso = new Date().toISOString();

    setConnections(prev => {
      const updated = prev.map(c => {
        if (connectionId && c.id !== connectionId) return c;
        return { ...c, lastFetchedAt: nowIso };
      });
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_CONNECTIONS, JSON.stringify(updated));
      } catch {}
      return updated;
    });

    setMarketplaceStoreProducts(prev => {
      const updated = prev.map(prod => {
        if (connectionId && prod.connectionId !== connectionId) return prod;
        return { ...prod, updatedAt: nowIso };
      });
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(updated));
      } catch {}
      return updated;
    });
    addToast({
      type: 'success',
      title: 'Channel Catalog Synced',
      message: 'Pulled latest external products and variants from connected store.'
    });
  };
const resetStoreProductsToDefaults = () => {
    setMarketplaceStoreProducts(INITIAL_MARKETPLACE_STORE_PRODUCTS);
    try {
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(INITIAL_MARKETPLACE_STORE_PRODUCTS));
    } catch {}
    addToast({
      type: 'info',
      title: 'Store Product Mappings Reset',
      message: 'Restored default demo mapping dataset.'
    });
  };
const importStoreProductToMaster = (storeProductId: string, storeVariantId?: string) => {
    const storeProd = marketplaceStoreProducts.find(p => p.id === storeProductId);
    if (!storeProd) return;

    const variantsToImport = storeVariantId 
      ? storeProd.variants.filter(v => v.id === storeVariantId)
      : storeProd.variants;

    if (variantsToImport.length === 0) return;

    let targetMasterProduct = products.find(
      p => p.title.toLowerCase().trim() === storeProd.title.toLowerCase().trim()
    );

    const isNewProduct = !targetMasterProduct;
    const nowIso = new Date().toISOString();

    const createdVariants: ProductVariant[] = variantsToImport.map((sv, idx) => {
      const masterSku = sv.channelSellerSku && sv.channelSellerSku.trim() !== ''
        ? sv.channelSellerSku.trim()
        : `JW-${storeProd.platform.toUpperCase().substring(0, 2)}-${Math.floor(1000 + Math.random() * 9000)}`;

      return {
        id: `var-${Date.now()}-${idx}`,
        sku: masterSku,
        title: sv.title || 'Standard',
        options: sv.specOptions || { 'Specification': sv.title || 'Standard' },
        price: sv.price,
        compareAtPrice: +(sv.price * 1.15).toFixed(2),
        costPrice: +(sv.price * 0.55).toFixed(2),
        inventoryQuantity: sv.stock > 0 ? sv.stock : 50,
        weightGrams: 500,
        barcode: `880${Math.floor(100000000 + Math.random() * 900000000)}`,
        image: sv.imageUrl || storeProd.imageUrl,
        enabled: true
      };
    });

    if (isNewProduct) {
      targetMasterProduct = {
        id: `prod-${Date.now()}`,
        title: storeProd.title,
        slug: storeProd.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || `jw-item-${Date.now()}`,
        brand: 'JW Audio',
        categoryId: categories[0]?.id || 'cat-headphones',
        collectionIds: [],
        descriptionHtml: `<p>${storeProd.title} - Imported from ${storeProd.shopName} (${storeProd.platform.toUpperCase()}).</p>`,
        summary: `Imported from ${storeProd.platform.toUpperCase()} store catalogue.`,
        images: [storeProd.imageUrl || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80'],
        status: 'active',
        hasVariants: createdVariants.length > 1,
        basePrice: createdVariants[0]?.price || 0,
        baseCompareAtPrice: createdVariants[0]?.compareAtPrice,
        options: [
          {
            id: `opt-${Date.now()}`,
            name: 'Specification',
            values: createdVariants.map(v => v.title)
          }
        ],
        variants: createdVariants,
        tags: ['Imported', storeProd.platform.toUpperCase()],
        totalInventory: createdVariants.reduce((sum, v) => sum + v.inventoryQuantity, 0),
        featured: false,
        createdAt: nowIso.split('T')[0],
        updatedAt: nowIso.split('T')[0]
      };

      setProducts(prev => {
        const updated = [targetMasterProduct!, ...prev];
        previewStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(updated));
        return updated;
      });
    } else {
      const updatedProduct: Product = {
        ...targetMasterProduct,
        variants: [...targetMasterProduct.variants, ...createdVariants],
        totalInventory: targetMasterProduct.totalInventory + createdVariants.reduce((sum, v) => sum + v.inventoryQuantity, 0),
        updatedAt: nowIso.split('T')[0]
      };

      setProducts(prev => {
        const updated = prev.map(p => p.id === targetMasterProduct!.id ? updatedProduct : p);
        previewStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(updated));
        return updated;
      });
      targetMasterProduct = updatedProduct;
    }

    setMarketplaceStoreProducts(prev => {
      const updated = prev.map(prod => {
        if (prod.id !== storeProductId) return prod;
        const updatedVariants = prod.variants.map((v, i) => {
          if (storeVariantId && v.id !== storeVariantId) return v;
          const correspondingMasterVar = createdVariants.find(
            cv => cv.title === v.title || cv.sku === v.channelSellerSku
          ) || createdVariants[i] || createdVariants[0];

          return {
            ...v,
            mappingStatus: 'mapped' as const,
            mappedJwProductId: targetMasterProduct!.id,
            mappedJwVariantId: correspondingMasterVar.id,
            mappedJwSku: correspondingMasterVar.sku,
            mappedJwProductName: targetMasterProduct!.title,
            mappedJwVariantTitle: correspondingMasterVar.title,
            mappedJwVariantImage: correspondingMasterVar.image,
            mappedJwStock: correspondingMasterVar.inventoryQuantity,
            mappedJwPrice: correspondingMasterVar.price,
            mappedAt: nowIso,
            mappedBy: user?.name ? `${user.name} (Auto Imported)` : 'Staff (Auto Imported)',
            suggestedMatch: undefined,
            conflictReason: undefined
          };
        });
        return { ...prod, variants: updatedVariants, updatedAt: nowIso };
      });
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(updated));
      } catch {}
      return updated;
    });

    createdVariants.forEach(v => {
      const existingInv = marketplaceInventory.find(inv => inv.masterSku === v.sku);
      if (!existingInv) {
        const newInvItem: MarketplaceInventoryItem = {
          id: `inv-imp-${v.id}`,
          masterSku: v.sku,
          barcode: v.barcode || `880${Math.floor(100000000 + Math.random() * 900000000)}`,
          productName: targetMasterProduct!.title,
          variantName: v.title,
          imageUrl: v.image || targetMasterProduct!.images[0],
          warehouseLocation: 'Zone A - Shelf 01',
          totalPhysicalStock: v.inventoryQuantity,
          reservedStock: 0,
          safetyStock: 5,
          availableStock: Math.max(0, v.inventoryQuantity - 5),
          reorderPoint: 10,
          costPrice: v.costPrice || 50,
          sellingPrice: v.price,
          channelMappings: {
            [storeProd.platform]: {
              isLinked: true,
              channelProductId: storeProd.channelProductId,
              channelSku: v.sku,
              channelSellerSku: v.sku,
              channelStock: v.inventoryQuantity,
              price: v.price,
              lastSyncedAt: nowIso,
              syncStatus: 'synced'
            }
          },
          autoSyncEnabled: true,
          historyLog: [
            {
              id: `log-imp-${Date.now()}`,
              timestamp: nowIso,
              action: `Imported from ${storeProd.shopName}`,
              change: v.inventoryQuantity,
              resultingStock: v.inventoryQuantity,
              user: user?.name || 'Staff User',
              reason: 'Store Catalog Import to Master'
            }
          ]
        };

        setMarketplaceInventory(prev => {
          const updated = [newInvItem, ...prev];
          previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
          return updated;
        });
      }
    });

    addToast({
      type: 'success',
      title: 'Imported to Master Catalog',
      message: `Created JW Master Product "${targetMasterProduct.title}" with ${createdVariants.length} SKU(s) and auto-linked.`
    });
  };
const batchImportStoreProductsToMaster = (storeProductIds: string[]) => {
    storeProductIds.forEach(id => {
      importStoreProductToMaster(id);
    });
    addToast({
      type: 'success',
      title: 'Batch Import Completed',
      message: `Successfully imported and linked ${storeProductIds.length} store product(s) to JW Master catalog.`
    });
  };
const publishMasterProductToStore = (
    productId: string, 
    connectionId: string, 
    options?: { priceMultiplier?: number; categoryName?: string }
  ) => {
    const product = products.find(p => p.id === productId);
    const connection = connections.find(c => c.id === connectionId) || connections[0];
    if (!product || !connection) {
      addToast({ type: 'error', title: 'Publish Failed', message: 'Master product or target store connection not found.' });
      return;
    }

    const multiplier = options?.priceMultiplier || 1.0;
    const nowIso = new Date().toISOString();
    const newExtId = `EXT-${Math.floor(1000000000 + Math.random() * 9000000000)}`;

    const newStoreProduct: MarketplaceStoreProduct = {
      id: `sp-pub-${Date.now()}`,
      connectionId: connection.id,
      platform: connection.platform,
      shopId: connection.shopId,
      shopName: connection.shopName,
      channelProductId: newExtId,
      title: product.title,
      imageUrl: product.images[0] || 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80',
      categoryName: options?.categoryName || 'Consumer Electronics & Audio',
      status: 'active',
      updatedAt: nowIso,
      variants: product.variants.map((v, i) => {
        const channelPrice = +(v.price * multiplier).toFixed(2);
        return {
          id: `spv-pub-${Date.now()}-${i}`,
          channelSkuId: `CSKU-${v.sku}`,
          channelSellerSku: v.sku,
          title: v.title,
          price: channelPrice,
          currency: 'MYR',
          stock: v.inventoryQuantity,
          imageUrl: v.image || product.images[0],
          mappingStatus: 'mapped',
          mappedJwProductId: product.id,
          mappedJwVariantId: v.id,
          mappedJwSku: v.sku,
          mappedJwProductName: product.title,
          mappedJwVariantTitle: v.title,
          mappedJwVariantImage: v.image || product.images[0],
          mappedJwStock: v.inventoryQuantity,
          mappedJwPrice: v.price,
          mappedAt: nowIso,
          mappedBy: user?.name ? `${user.name} (Push Listing)` : 'Staff (Push Listing)'
        };
      })
    };

    setMarketplaceStoreProducts(prev => {
      const updated = [newStoreProduct, ...prev];
      try {
        previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_STORE_PRODUCTS, JSON.stringify(updated));
      } catch {}
      return updated;
    });

    addSyncLog({
      platform: connection.platform,
      shopName: connection.shopName,
      eventType: 'product_publish',
      status: 'success',
      referenceId: newExtId,
      message: `Product "${product.title}" (${product.variants.length} variants) pushed and listed on ${connection.shopName}.`,
      payloadJson: JSON.stringify({ externalId: newExtId, jwProductId: product.id, variantsCount: product.variants.length }, null, 2),
      latencyMs: 310
    });

    addToast({
      type: 'success',
      title: 'Published to Channel',
      message: `Successfully listed "${product.title}" on ${connection.shopName} (${connection.platform.toUpperCase()}) and established 1:1 auto-link.`
    });
  };
const simulateIncomingOrder = (platform: MarketplacePlatform) => {
    const item = marketplaceInventory[0] || INITIAL_MARKETPLACE_INVENTORY[0];
    const buyers = [
      { name: 'Nurul Hidayah', username: '@nurul_tech_my', email: 'nurul.h@gmail.com', phone: '+60 18-992 0192', city: 'Subang Jaya', post: '47500' },
      { name: 'Tan Wei Ming', username: 'weiming_audiophile', email: 'weiming.tan@yahoo.com', phone: '+60 12-394 8291', city: 'Johor Bahru', post: '80100' },
      { name: 'Farhan Azman', username: '@farhan_sound', email: 'farhan.azman@gmail.com', phone: '+60 17-482 9102', city: 'Georgetown', post: '10200' },
      { name: 'Chloe Roberts', username: 'chloe.sound.studio', email: 'chloe.roberts@gmail.com', phone: '+1 415-555-0199', city: 'San Francisco', post: '94103' }
    ];
    const buyer = buyers[Math.floor(Math.random() * buyers.length)];
    const randomNum = Math.floor(100000 + Math.random() * 900000);
    const channelStore = connections.find(c => c.platform === platform) || connections[0];

    const newOrder: MarketplaceOrder = {
      id: `mord-${platform.substring(0, 2)}-${Date.now()}`,
      orderNumber: `ORD-${platform.substring(0, 2).toUpperCase()}-${randomNum}`,
      channel: platform,
      channelStoreId: channelStore.shopId,
      channelStoreName: channelStore.shopName,
      channelOrderId: `${Date.now()}${randomNum}`,
      marketplaceBuyerUsername: buyer.username,
      channelStatus: 'AWAITING_COLLECTION',
      slaShipDeadline: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
      isSlaBreachRisk: false,
      airwayBillGenerated: false,
      pickupType: 'dropoff',
      allocatedWarehouse: 'Warehouse Alpha (Central Hub - Cyberjaya)',
      createdAt: new Date().toISOString(),
      customerId: `cust-sim-${Date.now()}`,
      customerName: buyer.name,
      customerEmail: buyer.email,
      customerPhone: buyer.phone,
      paymentGateway: 'Credit Card',
      paymentGatewayStatus: 'paid',
      fulfillmentStatus: 'unfulfilled',
      currency: platform === 'shopify' ? 'USD' : 'MYR',
      subtotal: item.sellingPrice,
      taxAmount: 0,
      shippingAmount: 0,
      discountAmount: 0,
      totalAmount: item.sellingPrice,
      shippingAddress: {
        id: `addr-sim-${Date.now()}`,
        isDefault: true,
        type: 'shipping',
        firstName: buyer.name.split(' ')[0],
        lastName: buyer.name.split(' ').slice(1).join(' ') || 'Customer',
        address1: 'No 88, Jalan Teknologi 3/4',
        city: buyer.city,
        province: platform === 'shopify' ? 'CA' : 'Selangor',
        country: platform === 'shopify' ? 'United States' : 'Malaysia',
        postalCode: buyer.post,
        phone: buyer.phone
      },
      billingAddress: {
        id: `addr-sim-b-${Date.now()}`,
        isDefault: true,
        type: 'billing',
        firstName: buyer.name.split(' ')[0],
        lastName: buyer.name.split(' ').slice(1).join(' ') || 'Customer',
        address1: 'No 88, Jalan Teknologi 3/4',
        city: buyer.city,
        province: platform === 'shopify' ? 'CA' : 'Selangor',
        country: platform === 'shopify' ? 'United States' : 'Malaysia',
        postalCode: buyer.post,
        phone: buyer.phone
      },
      items: [
        {
          id: `item-sim-${Date.now()}`,
          productId: 'prod-01',
          variantId: 'var-01-blk',
          title: item.productName,
          variantTitle: item.variantName,
          sku: item.masterSku,
          price: item.sellingPrice,
          quantity: 1,
          image: item.imageUrl,
          weightGrams: 420
        }
      ],
      timeline: [
        {
          id: `evt-${Date.now()}`,
          timestamp: new Date().toISOString(),
          type: 'created',
          title: `Simulated ${platform.toUpperCase()} Order Inbound`,
          description: `Order webhook received from ${channelStore.shopName}. Reserved 1 unit of ${item.masterSku}.`
        }
      ]
    };

    // Add to marketplace orders
    setMarketplaceOrders(prev => {
      const updated = [newOrder, ...prev];
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_ORDERS, JSON.stringify(updated));
      return updated;
    });

    // Auto-reserve stock
    setMarketplaceInventory(prev => {
      const updated = prev.map(inv => {
        if (inv.masterSku === item.masterSku) {
          const newReserved = inv.reservedStock + 1;
          const newAvail = Math.max(0, inv.totalPhysicalStock - newReserved - inv.safetyStock);
          const newLog = {
            id: `hl-${Date.now()}`,
            timestamp: new Date().toISOString(),
            action: 'ORDER_RESERVATION',
            change: -1,
            resultingStock: newAvail,
            user: `${platform.toUpperCase()} Webhook`,
            reason: `Order #${newOrder.orderNumber} auto-reserved 1 unit`
          };
          return {
            ...inv,
            reservedStock: newReserved,
            availableStock: newAvail,
            historyLog: [newLog, ...inv.historyLog]
          };
        }
        return inv;
      });
      previewStorage.setItem(STORAGE_KEYS.MARKETPLACE_INVENTORY, JSON.stringify(updated));
      return updated;
    });

    addToast({
      type: 'success',
      title: `⚡ New ${platform.toUpperCase()} Order Received!`,
      message: `Order #${newOrder.orderNumber} from ${buyer.name} (${channelStore.shopName}). 1 unit reserved in WMS.`
    });

    addSyncLog({
      platform,
      shopName: channelStore.shopName,
      eventType: 'order_created',
      status: 'success',
      referenceId: newOrder.channelOrderId,
      message: `Inbound order webhook triggered. Reserved 1 unit for SKU ${item.masterSku}.`,
      payloadJson: JSON.stringify({ orderId: newOrder.channelOrderId, buyer: buyer.username, total: newOrder.totalAmount }, null, 2),
      latencyMs: 95
    });
  };
return {marketplaceStoreProducts,setCurrentSection,saveConnection,reconnectConnection,addToast,disconnectConnection,connections,marketplaceOrders,marketplaceInventory,syncLogs,deleteConnection,toggleConnectionSync,refreshToken,syncAllMarketplaces,saveInventoryItem,adjustStock,broadcastStockToChannels,broadcastAllStockToChannels,linkChannelSku,unlinkChannelSku,products,mapStoreVariantToJwSku,unmapStoreVariant,batchAcceptSuggestedMappings,refreshStoreProductsFromChannel,resetStoreProductsToDefaults,importStoreProductToMaster,batchImportStoreProductsToMaster,selectedMarketplaceOrderId,setSelectedMarketplaceOrderId,generateAirwayBill,markOrderPacked,markOrderShipped,batchPrintAWB,batchPackOrders,simulateIncomingOrder,retrySyncLog,clearSyncLogs,categories,publishMasterProductToStore,currentSection,toasts,removeToast, triggerChannelSyncAll: undefined as unknown as () => void, isSyncingMarketplace: false};
}
export const AdminProvider: React.FC<{children: React.ReactNode}> = ({children}) => <PreviewContext.Provider value={usePreviewModel()}>{children}</PreviewContext.Provider>;
