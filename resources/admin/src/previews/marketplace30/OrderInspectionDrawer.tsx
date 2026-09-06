import React from 'react';
import {MapPin, Printer, Truck, X} from 'lucide-react';
import {MarketplaceLogo} from './components/marketplace/MarketplaceLogo';
import type {MarketplaceOrderDetail} from '../../integration/marketplaceOperations';

// Presentation copied from ZIP30 MarketplaceOMSPage's Order Inspection Drawer.
// Only data expressions, disabled actions and close accessibility have changed.
export function OrderInspectionDrawer({order, onClose}: {order: MarketplaceOrderDetail; onClose: () => void}) {
  const recipient = order.recipient;
  const activeOrder = {
    channel: order.platform, orderNumber: order.external_order_id, channelOrderId: order.external_order_id,
    channelStoreName: order.shop_name, channelStatus: order.platform_status, currency: order.currency ?? '—',
    marketplaceBuyerUsername: '', customerName: recipient?.name ?? 'Not provided by platform',
    customerPhone: recipient?.phone,
    shippingAddress: {firstName: recipient?.name ?? 'Not provided by platform', lastName: '',
      address1: recipient?.full_address ?? 'Not provided by platform', city: recipient?.city ?? '',
      province: recipient?.state ?? '', postalCode: recipient?.zipcode ?? '', country: recipient?.region ?? ''},
    items: order.lines.map(line => ({title: line.title ?? 'Not provided by platform',
      sku: line.jw_sku ?? 'Unmapped', variantTitle: line.variant_title, quantity: line.quantity ?? '—',
      price: line.unit_price, image: null as string | null})),
    timeline: [{title: order.platform_status, timestamp: new Date(order.platform_updated_at * 1000).toISOString(),
      description: 'Latest platform snapshot; not a locally executed fulfillment event.'}],
  };
  return <>      {activeOrder && (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/50 backdrop-blur-xs">
          <div className="w-full max-w-xl bg-white dark:bg-neutral-900 h-full shadow-2xl flex flex-col border-l border-neutral-200 dark:border-neutral-800 overflow-hidden animate-in slide-in-from-right duration-200">
            {/* Drawer Header */}
            <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between bg-neutral-50 dark:bg-neutral-950/50">
              <div>
                <div className="flex items-center gap-2.5">
                  <MarketplaceLogo platform={activeOrder.channel} variant="badge" />
                  <h3 className="font-black text-base text-neutral-900 dark:text-white font-mono">
                    #{activeOrder.orderNumber}
                  </h3>
                </div>
                <p className="text-xs text-neutral-500 font-mono mt-0.5">
                  Platform ID: {activeOrder.channelOrderId} • Store: {activeOrder.channelStoreName}
                </p>
              </div>

              <button
                onClick={onClose}
                aria-label="Close order details"
                className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Drawer Content */}
            <div className="p-6 overflow-y-auto space-y-6 text-xs flex-1">
              {/* Quick Actions Toolbar */}
              <div className="p-4 bg-indigo-50/50 dark:bg-indigo-950/30 rounded-2xl border border-indigo-100 dark:border-indigo-900/50 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="font-bold text-neutral-900 dark:text-white">Fulfillment Station</p>
                  <p className="text-[11px] text-neutral-500">AWB status: {'Not available — platform integration pending'}</p>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    disabled title="Platform airway bill integration is not available yet"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-xs cursor-pointer"
                  >
                    <Printer className="w-3.5 h-3.5" />
                    Print AWB Label
                  </button>
                  {activeOrder.channelStatus !== 'IN_TRANSIT' && (
                    <button
                      disabled title="Platform fulfillment integration is not available yet"
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-bold shadow-xs cursor-pointer"
                    >
                      <Truck className="w-3.5 h-3.5" />
                      Dispatch
                    </button>
                  )}
                </div>
              </div>

              {/* Warehouse & Shipping Address */}
              <div className="grid grid-cols-2 gap-3">
                <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                  <span className="text-[10px] font-bold uppercase text-neutral-400">Allocated WMS Hub</span>
                  <p className="font-bold text-neutral-900 dark:text-white">{'Not allocated'}</p>
                  <p className="text-[11px] text-neutral-500">Dropoff / Pickup: {'Not provided'}</p>
                </div>

                <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                  <span className="text-[10px] font-bold uppercase text-neutral-400">Buyer Marketplace Profile</span>
                  <p className="font-bold text-neutral-900 dark:text-white">{activeOrder.marketplaceBuyerUsername || activeOrder.customerName}</p>
                  <p className="text-[11px] text-neutral-500">{activeOrder.customerPhone || 'Not provided by platform'}</p>
                </div>
              </div>

              {/* Shipping Address */}
              <div className="p-4 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
                <div className="flex items-center gap-1.5 text-neutral-700 dark:text-neutral-300 font-bold mb-1">
                  <MapPin className="w-3.5 h-3.5 text-indigo-500" />
                  <span>Consignee Delivery Address</span>
                </div>
                <p className="font-semibold text-neutral-900 dark:text-white">{activeOrder.shippingAddress.firstName} {activeOrder.shippingAddress.lastName}</p>
                <p className="text-neutral-600 dark:text-neutral-400">{activeOrder.shippingAddress.address1}</p>
                <p className="text-neutral-600 dark:text-neutral-400">{activeOrder.shippingAddress.city}, {activeOrder.shippingAddress.province} {activeOrder.shippingAddress.postalCode}</p>
                <p className="text-neutral-600 dark:text-neutral-400">{activeOrder.shippingAddress.country}</p>
              </div>

              {/* Items List */}
              <div className="space-y-3">
                <h4 className="font-bold text-neutral-900 dark:text-white">Ordered Items ({activeOrder.items?.length || 0})</h4>
                <div className="space-y-2">
                  {(activeOrder.items || []).map((item, idx) => (
                    <div key={idx} className="p-3 bg-neutral-50 dark:bg-neutral-950 rounded-xl border border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        {item.image && (
                          <img src={item.image} alt={item.title} referrerPolicy="no-referrer" className="w-11 h-11 rounded-lg object-cover border border-neutral-200 dark:border-neutral-800" />
                        )}
                        <div>
                          <p className="font-bold text-neutral-900 dark:text-white">{item.title}</p>
                          <p className="text-[11px] font-mono text-neutral-500">Master SKU: {item.sku} {item.variantTitle ? `• ${item.variantTitle}` : ''}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <span className="font-bold text-neutral-900 dark:text-white">x{item.quantity}</span>
                        <p className="text-neutral-500 font-semibold">{activeOrder.currency} {item.price === null ? '—' : Number(item.price).toFixed(2)}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Order Timeline Audit */}
              <div className="space-y-3">
                <h4 className="font-bold text-neutral-900 dark:text-white">Fulfillment Timeline & Webhook Events</h4>
                <div className="space-y-2 relative border-l-2 border-indigo-200 dark:border-indigo-900 ml-2 pl-4">
                  {(activeOrder.timeline || []).map((evt, idx) => (
                    <div key={idx} className="relative pb-2">
                      <div className="w-2.5 h-2.5 rounded-full bg-indigo-600 absolute -left-[21px] top-1" />
                      <div className="flex items-center justify-between">
                        <p className="font-bold text-neutral-900 dark:text-white text-[11px]">{evt.title}</p>
                        <span className="text-[10px] text-neutral-400">{new Date(evt.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                      </div>
                      <p className="text-[11px] text-neutral-600 dark:text-neutral-400 mt-0.5">{evt.description}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}


  </>;
}
