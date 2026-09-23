import type { MarketplaceSyncLog } from '../types';

export async function fetchHubEventLogs(): Promise<MarketplaceSyncLog[]> {
  const response = await fetch('/admin/api/marketplace/hub-events', {
    credentials: 'same-origin', headers: { Accept: 'application/json' },
  });
  if (!response.ok) throw new Error('Event ledger unavailable');
  const data = await response.json();
  return (data.events || []).map((event: any): MarketplaceSyncLog => ({
    id: String(event.id), timestamp: event.receivedAt, platform: event.platform,
    shopName: event.storeName, eventType: event.type === 'ORDER_STATUS_CHANGED' ? 'order_status_update' : 'order_created', status: event.status,
    referenceId: event.externalOrderId,
    message: event.attentionCode === 'inventory_line_skipped' ? 'An unmapped or historical SKU was skipped; stock was not reserved for that item'
      : event.attentionCode ? `Inventory requires attention: ${event.attentionCode}`
      : event.deliveryStatus === 'completed' ? 'Order event processed by Ecom'
      : event.deliveryStatus === 'failed' ? 'Order processing failed' : 'Awaiting order processing',
    payloadJson: '', latencyMs: 0,
  }));
}
