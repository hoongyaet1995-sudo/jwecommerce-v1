import React from 'react';
import { MarketplaceOrder } from '../../types';
import { MarketplaceLogo } from './MarketplaceLogo';
import { X, Printer, CheckCircle2, ShieldCheck } from 'lucide-react';

interface ShippingLabelModalProps {
  order: MarketplaceOrder | null;
  isOpen: boolean;
  onClose: () => void;
  onPrintComplete?: () => void;
}

export const ShippingLabelModal: React.FC<ShippingLabelModalProps> = ({
  order,
  isOpen,
  onClose,
  onPrintComplete
}) => {
  if (!isOpen || !order) return null;

  const getPlatformHeader = () => {
    switch (order.channel) {
      case 'tiktok':
        return {
          name: 'TikTok Shop Shipping',
          courier: order.trackingCarrier || 'J&T Express (TikTok Dropoff)',
          accent: 'bg-black text-white',
          tag: 'TIKTOK-EXPRESS-MY'
        };
      case 'shopee':
        return {
          name: 'Shopee Supported Logistics',
          courier: order.trackingCarrier || 'Shopee Xpress (SPX Express)',
          accent: 'bg-amber-600 text-white',
          tag: 'SPX-STANDARD-DO'
        };
      case 'lazada':
        return {
          name: 'Lazada Logistics (LEX)',
          courier: order.trackingCarrier || 'LEX Express',
          accent: 'bg-blue-700 text-white',
          tag: 'LEX-MY-KL'
        };
      case 'shopify':
      default:
        return {
          name: 'Shopify Shipping',
          courier: order.trackingCarrier || 'DHL Express Worldwide',
          accent: 'bg-emerald-700 text-white',
          tag: 'DHL-EXPRESS-AIR'
        };
    }
  };

  const info = getPlatformHeader();

  const handlePrint = () => {
    window.print();
    if (onPrintComplete) onPrintComplete();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-2xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden my-8">
        {/* Header toolbar */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
              <Printer className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-bold text-neutral-900 dark:text-white">
                  Official Marketplace Airway Bill (AWB)
                </h3>
                <MarketplaceLogo platform={order.channel} size={16} />
              </div>
              <p className="text-xs text-neutral-500">
                Order #{order.orderNumber} • {(order.channel || 'MARKETPLACE').toUpperCase()} Open API
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              Print Label (4x6")
            </button>
            <button
              onClick={onClose}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* 4x6 Physical AWB Printable Template Card */}
        <div className="p-6 bg-neutral-100 dark:bg-neutral-950 flex justify-center">
          <div
            id="printable-shipping-label"
            className="w-full max-w-md bg-white text-black p-6 rounded-lg border-2 border-dashed border-neutral-400 shadow-lg space-y-4 font-mono text-xs select-none"
            style={{ fontFamily: '"Courier New", Courier, monospace' }}
          >
            {/* Carrier Banner */}
            <div className="flex items-center justify-between border-b-2 border-black pb-3">
              <div className="flex items-center gap-2.5">
                <div className="p-1 rounded bg-neutral-100 border border-neutral-300">
                  <MarketplaceLogo platform={order.channel} size={24} />
                </div>
                <div>
                  <span className="text-lg font-black tracking-tight">{(info.name || 'CARRIER').toUpperCase()}</span>
                  <p className="text-[11px] font-bold text-neutral-700">{info.courier}</p>
                </div>
              </div>
              <div className="text-right">
                <span className="inline-block px-2 py-1 bg-black text-white font-black text-xs rounded">
                  {info.tag}
                </span>
                <p className="text-[10px] text-neutral-600 mt-1">STANDARD DELIVERY</p>
              </div>
            </div>

            {/* Tracking Barcode Area */}
            <div className="text-center py-2 border-b-2 border-black">
              <div className="h-14 bg-neutral-900 text-white flex items-center justify-center font-mono font-bold tracking-widest text-base px-2">
                |||| | |||||| || | |||||||| || |||||| | |||| |
              </div>
              <p className="font-bold text-sm tracking-wider mt-1.5">
                TRACKING: {order.trackingNumber || 'MY-AWB-PENDING'}
              </p>
              <p className="text-[10px] text-neutral-500">
                Channel Order Ref: {order.channelOrderId}
              </p>
            </div>

            {/* Routing Code & Zone Matrix */}
            <div className="grid grid-cols-3 gap-2 border-b-2 border-black pb-2 text-center">
              <div className="border-r border-black pr-1">
                <p className="text-[9px] text-neutral-600">SORT CODE</p>
                <p className="font-black text-base">KUL-08</p>
              </div>
              <div className="border-r border-black px-1">
                <p className="text-[9px] text-neutral-600">HUB DEST</p>
                <p className="font-black text-base">{order.shippingAddress.postalCode || '47500'}</p>
              </div>
              <div className="pl-1">
                <p className="text-[9px] text-neutral-600">DISPATCH TYPE</p>
                <p className="font-black text-base">{(order.pickupType || 'DROPOFF').toUpperCase()}</p>
              </div>
            </div>

            {/* Shipper & Consignee Addresses */}
            <div className="grid grid-cols-2 gap-3 border-b-2 border-black pb-3 text-[11px]">
              <div>
                <p className="font-bold uppercase text-[9px] text-neutral-500">FROM (SHIPPER):</p>
                <p className="font-bold">AURORA CENTRAL HUB</p>
                <p className="text-neutral-700">Cyberjaya Logistics Park</p>
                <p className="text-neutral-700">Selangor, Malaysia</p>
                <p className="text-neutral-700">Tel: +60 3-8890 1200</p>
              </div>
              <div>
                <p className="font-bold uppercase text-[9px] text-neutral-500">TO (CONSIGNEE):</p>
                <p className="font-bold">{order.shippingAddress.firstName} {order.shippingAddress.lastName}</p>
                <p className="text-neutral-700">{order.shippingAddress.address1}</p>
                <p className="text-neutral-700">{order.shippingAddress.city}, {order.shippingAddress.province} {order.shippingAddress.postalCode}</p>
                <p className="text-neutral-700">Buyer: {order.marketplaceBuyerUsername || order.customerEmail}</p>
                <p className="text-neutral-700 font-bold">Tel: {order.shippingAddress.phone || order.customerPhone}</p>
              </div>
            </div>

            {/* Package Contents & SKU breakdown */}
            <div className="border-b-2 border-black pb-2 text-[10px]">
              <p className="font-bold uppercase text-[9px] text-neutral-500 mb-1">PACKAGE CONTENT / ITEM LIST:</p>
              <table className="w-full text-left">
                <thead>
                  <tr className="border-b border-neutral-300">
                    <th className="py-0.5">SKU / Item</th>
                    <th className="py-0.5 text-center">Qty</th>
                    <th className="py-0.5 text-right">Price</th>
                  </tr>
                </thead>
                <tbody>
                  {(order.items || []).map((item, idx) => (
                    <tr key={idx}>
                      <td className="py-0.5 truncate max-w-[160px] font-semibold">{item.sku} - {item.title}</td>
                      <td className="py-0.5 text-center font-bold">{item.quantity}</td>
                      <td className="py-0.5 text-right">{order.currency} {item.price.toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Footer declaration */}
            <div className="flex items-center justify-between pt-1 text-[9px] text-neutral-600">
              <div className="flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5 text-green-700" />
                <span>Security Inspection Cleared • Aurora WMS verified</span>
              </div>
              <div>
                <span>Weight: {(order.items || []).reduce((s, i) => s + (i.weightGrams || 350) * i.quantity, 0)}g</span>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Actions */}
        <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
          <div className="flex items-center gap-2 text-xs text-neutral-500">
            <CheckCircle2 className="w-4 h-4 text-emerald-500" />
            <span>Tracking status will automatically write back to {(order.channel || 'Marketplace').toUpperCase()} Open API upon courier pickup.</span>
          </div>
          <button
            onClick={onClose}
            className="px-4 py-2 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

