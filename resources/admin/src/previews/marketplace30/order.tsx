import React, {useEffect, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {OrderInspectionDrawer} from './OrderInspectionDrawer';
import {marketplaceOperations, type MarketplaceOrderDetail} from '../../integration/marketplaceOperations';
import './source.css';

function OrderPreview() {
  const [order, setOrder] = useState<MarketplaceOrderDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    let active = true;
    const id = window.location.pathname.match(/\/marketplace\/orders\/(\d+)\/preview$/)?.[1];
    if (!id) {setError('Order identity is missing.'); return;}
    marketplaceOperations.orderDetail(id, {includeRecipient: true})
      .then(result => {if (active) setOrder(result.data);})
      .catch(() => {if (active) setError('Order details could not be loaded. Check access and try again.');});
    return () => {active = false;};
  }, []);
  const close = () => window.location.assign('/admin/marketplace/oms');
  if (order) return <OrderInspectionDrawer order={order} onClose={close}/>;
  return <div className="p-6 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
    <p role={error ? 'alert' : 'status'} className="font-bold text-neutral-900 dark:text-white">{error ?? 'Loading order details…'}</p>
    <button onClick={close} className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer">Return to Orders</button>
  </div>;
}
createRoot(document.getElementById('root')!).render(<OrderPreview/>);
