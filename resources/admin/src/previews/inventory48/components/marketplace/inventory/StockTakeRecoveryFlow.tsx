import { warehouseDisplayCode } from './warehouseDisplay';
import React, { useEffect, useState } from 'react';
import { Boxes, CheckCircle2, X } from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { storeInventoryApi } from '../../../../../integration/storeInventory';
import { MarketplaceLogo } from '../MarketplaceLogo';

type Order = { id: number; reference: string; status: string; line_key: string; historical: boolean; can_review_historical: boolean; review_decision?: string | null };
type Snapshot = { eligible: boolean; blockers: { code: string }[]; orders: Order[] };

export const stockTakeBlockerCopy: Record<string, string> = {
  open_test_store_order: 'Open orders need review before this warehouse can be counted.',
  unreadable_test_store_order: 'An order cannot be read. Resolve it in Orders before counting.',
  legacy_stock_present: 'Existing stock belongs to this location. Reconcile its ledger before First Count.',
  active_stock_obligation: 'An active reservation must be completed or released in Orders first.',
  unfinished_stock_sync: 'A previous stock sync is still open. Check its status before counting.',
  physical_facility_unlinked: 'This marketplace warehouse needs a verified stock location.',
  physical_facility_shared: 'This physical stock location is assigned to another platform warehouse.',
  mapping_missing: 'Connect this marketplace listing to exactly one JW SKU.',
  provider_topology_unverified: 'Refresh and verify the marketplace warehouse list.',
  test_seller_not_approved: 'Reconnect or verify this test store in Connect Store.',
  test_acceptance_disabled: 'Stock Take is unavailable for this test connection. Ask an administrator to check store settings.',
  facility_not_in_store: 'This warehouse is no longer registered with the selected store.',
};

interface Props {
  item: MarketplaceInventoryItem;
  store: { id: string; name: string };
  facility: { id: string; name: string; providerId: string };
  onClose: () => void;
  onSaved: () => void;
  resolveOnly?: boolean;
}

export const StockTakeRecoveryFlow: React.FC<Props> = ({ item, store, facility, onClose, onSaved, resolveOnly = false }) => {
  const [screen, setScreen] = useState(0);
  const [snapshot, setSnapshot] = useState<Snapshot | null>(null);
  const [choices, setChoices] = useState<Record<string, string>>({});
  const [reason, setReason] = useState('');
  const [count, setCount] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [receipt, setReceipt] = useState<any>(null);
  const [sync, setSync] = useState<any>(null);
  const [syncBusy, setSyncBusy] = useState(false);
  const variantId = Number((item as any).variantId);
  const facilityId = Number(facility.id);
  const refresh = async () => {
    const result = await storeInventoryApi.stockTakeRecovery(store.id, facilityId, variantId);
    setSnapshot(result);
    return result as Snapshot;
  };
  useEffect(() => {
    let active = true;
    storeInventoryApi.stockTakeRecovery(store.id, facilityId, variantId)
      .then(result => { if (active) setSnapshot(result); })
      .catch((failure: Error) => { if (active) setError(failure.message); });
    return () => { active = false; };
  }, [store.id, facilityId, variantId]);

  const unresolved = snapshot?.orders.filter(order => !order.historical) || [];
  const choiceKey = (order: Order) => `${order.id}:${order.line_key}`;
  const otherBlockers = (snapshot?.blockers || []).filter(blocker => blocker.code !== 'open_test_store_order');
  const countValid = /^\d+$/.test(count) && Number(count) <= 2147483647;
  const next = async () => {
    setError('');
    if (screen === 0) { setScreen(unresolved.length ? 1 : 2); return; }
    if (screen === 1) {
      if (unresolved.some(order => !['historical', 'risk_accepted'].includes(choices[choiceKey(order)]) || !order.can_review_historical)) {
        setError('Allocated or unreadable order lines must be resolved in Orders before Stock Take.'); return;
      }
      if (reason.trim().length < 10) { setError('Enter the evidence for this historical-order decision.'); return; }
      setBusy(true);
      try {
        const result = await storeInventoryApi.reviewHistoricalStockTakeOrders(store.id, facilityId, variantId,
          unresolved.map(order => ({ order_id: order.id, line_key: order.line_key,
            decision: choices[choiceKey(order)] as 'historical' | 'risk_accepted' })), reason.trim()) as Snapshot;
        setSnapshot(result);
        if (resolveOnly && result.eligible) { onSaved(); onClose(); return; }
        if (!result.eligible) { setError('Other stock issues still need attention before First Count.'); return; }
        setScreen(2);
      } catch (failure: any) { setError(failure.message); }
      finally { setBusy(false); }
      return;
    }
    if (screen === 2) { if (countValid) setScreen(3); return; }
    if (screen === 3) {
      if (!countValid) return;
      setBusy(true);
      try {
        const current = await refresh();
        if (!current.eligible) throw new Error('Stock or order status changed. Review this location again.');
        const result = await storeInventoryApi.facilityStockAction(store.id, facilityId, variantId,
          'count', Number(count), 'Verified warehouse Stock Take');
        setReceipt(result);
        setScreen(4);
        onSaved();
      } catch (failure: any) { setError(failure.message); }
      finally { setBusy(false); }
    }
  };
  const checkSync = async () => {
    setSyncBusy(true); setError('');
    try { setSync(await storeInventoryApi.facilitySyncCheck(store.id, facilityId, variantId)); }
    catch (failure: any) { setError(failure.message); }
    finally { setSyncBusy(false); }
  };
  const titles = ['Resolve stock issue', 'Review order obligations', 'Stock Take', 'Review Stock Take', 'Stock Take recorded'];
  const descriptions = [
    'See what needs attention for this SKU and warehouse.',
    'Review each order line. A supervisor may accept the oversell risk for an active order.',
    'Enter the quantity physically counted at this warehouse.',
    'Confirm this one stock location before recording the count.',
    'JW ledger and marketplace confirmation are tracked separately.',
  ];
  const canContinue = !!snapshot && !busy && (screen === 0 ? otherBlockers.length === 0
    : screen === 1 ? unresolved.every(order => ['historical', 'risk_accepted'].includes(choices[choiceKey(order)]) && order.can_review_historical) && reason.trim().length >= 10
      : screen === 2 ? countValid && snapshot.eligible : screen === 3 ? countValid : false);

  return <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-3 text-neutral-100 sm:p-6">
    <div role="dialog" aria-modal="true" aria-label="Stock Take" className="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-neutral-700 bg-neutral-900 shadow-2xl">
      <header className="flex items-start justify-between gap-4 border-b border-neutral-800 px-5 py-5 sm:px-6">
        <div><p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-purple-300">Manage Stock · Independent mode</p>
          <h2 className="text-lg font-bold">{titles[screen]}</h2><p className="mt-1 text-xs text-neutral-400">{descriptions[screen]}</p></div>
        <button type="button" onClick={onClose} aria-label="Close Stock Take" className="text-neutral-400 hover:text-white"><X className="h-5 w-5" /></button>
      </header>
      <div className="h-0.5 bg-neutral-800"><div className="h-full bg-purple-500" style={{ width: `${Math.min(screen + 1, 4) * 25}%` }} /></div>
      <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 text-xs sm:px-6">
        <div className="flex items-center gap-3 rounded-xl border border-neutral-700 bg-neutral-800 p-3">
          <div className="flex h-11 w-11 items-center justify-center overflow-hidden rounded-lg bg-neutral-700">
            {item.imageUrl ? <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" /> : <Boxes className="h-5 w-5 text-neutral-400" />}
          </div><div><strong className="block">{item.masterSku}</strong><span className="text-neutral-400">{item.productName}{item.variantName ? ` · ${item.variantName}` : ''}</span></div>
        </div>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <div className="rounded-lg border border-neutral-700 p-3"><span className="block text-neutral-400">Marketplace store</span><strong className="mt-1 flex items-center gap-2"><MarketplaceLogo platform="lazada" size="sm" />{store.name}</strong></div>
          <div className="rounded-lg border border-neutral-700 p-3"><span className="block text-neutral-400">Stock location</span><strong className="mt-1 block">{facility.name} · {warehouseDisplayCode(facility.providerId)}</strong></div>
        </div>
        {!snapshot && !error && <p role="status">Checking stock and orders…</p>}
        {screen === 0 && snapshot && <><h3 className="text-base font-bold">Stock location check</h3>
          {(snapshot.blockers.length ? snapshot.blockers : [{ code: 'ready' }]).map((blocker, index) =>
            <div key={`${blocker.code}-${index}`} className="rounded-xl border border-neutral-700 bg-neutral-800 p-4">
              <strong>{blocker.code === 'ready' ? 'Ready for Stock Take' : stockTakeBlockerCopy[blocker.code] || `Review required: ${blocker.code.replaceAll('_', ' ')}`}</strong>
              {blocker.code === 'open_test_store_order' && <p className="mt-1 text-neutral-400">{unresolved.length} matching order line{unresolved.length === 1 ? '' : 's'} found.</p>}
              {blocker.code === 'mapping_missing' && <a className="mt-2 block font-semibold text-purple-300 underline" href="/admin/marketplace/mapping">Open SKU mapping</a>}
              {blocker.code === 'test_seller_not_approved' && <a className="mt-2 block font-semibold text-purple-300 underline" href="/admin/marketplace/auth">Open Connect Store</a>}
              {blocker.code === 'unfinished_stock_sync' && <button type="button" disabled={syncBusy} onClick={() => void checkSync()} className="mt-2 font-semibold text-purple-300 underline">Check stock sync</button>}
            </div>)}
          {otherBlockers.length > 0 && <p className="text-amber-300">Resolve these issues through their existing inventory or order workflows before counting. Other locations are unchanged.</p>}
          {sync && <p role="status" className="rounded-xl border border-neutral-700 p-3">Marketplace sync: {sync.status || 'Status unavailable'}</p>}
        </>}
        {screen === 1 && <><h3 className="text-base font-bold">Which orders still need stock?</h3>
          <p className="text-neutral-400">Choose a resolution for every order line. Risk acceptance excludes that line from JW reservations and can cause overselling. The decision and reason are audited.</p>
          {unresolved.map(order => <label key={choiceKey(order)} className="flex flex-col gap-2 rounded-xl border border-neutral-700 bg-neutral-800 p-4 sm:flex-row sm:items-center sm:justify-between">
            <span><strong className="block">Order {order.reference || `#${order.id}`}</strong><span className="text-neutral-400">{order.status} · Line {order.line_key || 'unknown'}</span></span>
            <select aria-label={`Resolution for order ${order.reference || order.id} line ${order.line_key}`} value={choices[choiceKey(order)] || ''}
              onChange={event => setChoices(previous => ({ ...previous, [choiceKey(order)]: event.target.value }))}
              className="rounded-lg border border-neutral-600 bg-neutral-900 px-3 py-2 text-xs">
              <option value="">Choose resolution</option><option value="active">Resolve in Orders</option>
              {order.can_review_historical && <><option value="historical">Historical — outside managed stock</option><option value="risk_accepted">Accept oversell risk — continue</option></>}
            </select>
          </label>)}
          <label className="block font-semibold">Supervisor decision and evidence
            <textarea value={reason} onChange={event => setReason(event.target.value)} rows={3}
              placeholder="Explain the order-line decision and any accepted oversell risk."
              className="mt-2 w-full rounded-lg border border-neutral-600 bg-neutral-800 p-3 text-xs" /></label>
        </>}
        {screen === 2 && <><h3 className="text-base font-bold">How many units are here now?</h3>
          <p className="text-neutral-400">Physically count this warehouse. Do not copy the marketplace quantity.</p>
          <label className="block font-semibold">Verified warehouse physical count
            <input type="number" min="0" max="2147483647" step="1" value={count} onChange={event => setCount(event.target.value)}
              aria-label="Verified warehouse physical count" className="mt-2 block w-full rounded-lg border border-neutral-600 bg-neutral-800 p-3 text-lg" /></label>
          <p className="rounded-xl border border-purple-800 bg-purple-950/30 p-4 text-purple-200">Only this SKU, store and warehouse will be counted. Existing audit history remains intact.</p>
        </>}
        {screen === 3 && <><h3 className="text-base font-bold">Review this one location</h3>
          <div className="rounded-xl border border-neutral-700 bg-neutral-800 p-4"><span className="text-neutral-400">Verified physical count</span><strong className="float-right">{count} units</strong></div>
          <div className="rounded-xl border border-neutral-700 bg-neutral-800 p-4"><span className="text-neutral-400">Order lines reviewed</span><strong className="float-right">{snapshot?.orders.filter(order => order.review_decision).length || 0}</strong></div>
          <p className="text-neutral-400">Confirmation saves a reviewed JW ledger entry and queues the existing marketplace stock sync. Provider confirmation is checked separately.</p>
        </>}
        {screen === 4 && <><div className="py-5 text-center"><CheckCircle2 className="mx-auto h-7 w-7 text-emerald-400" />
          <h3 className="mt-3 text-base font-bold">JW Stock Take recorded</h3><p className="mt-1 text-neutral-400">{count} units were saved for this location.</p></div>
          <div className="rounded-xl border border-neutral-700 bg-neutral-800 p-4"><strong>JW ledger</strong><span className="float-right text-emerald-400">{receipt?.ledger_status || 'applied'}</span></div>
          <div className="rounded-xl border border-neutral-700 bg-neutral-800 p-4"><strong>Marketplace sync</strong>
            <span className="float-right">{sync?.status || receipt?.provider_sync_status || 'pending'}</span>
            <p className="mt-2 text-neutral-400">Use Check status to request provider confirmation for this exact warehouse.</p>
            <button type="button" disabled={syncBusy} onClick={() => void checkSync()} className="mt-3 rounded-lg border border-neutral-600 px-3 py-2 disabled:opacity-40">Check status</button>
          </div>
        </>}
        {error && <p role="alert" className="rounded-lg border border-rose-800 bg-rose-950/30 p-3 text-rose-300">{error}</p>}
      </div>
      <footer className="flex items-center justify-between border-t border-neutral-800 px-5 py-4 sm:px-6">
        <span className="text-[10px] text-neutral-400">{screen === 4 ? 'COMPLETE' : `STEP ${screen + 1} OF 4`}</span>
        <div className="flex gap-2"><button type="button" onClick={() => screen === 0 || screen === 4 ? onClose() : setScreen(screen - 1)} className="rounded-lg border border-neutral-600 px-4 py-2">{screen === 0 || screen === 4 ? 'Close' : 'Back'}</button>
          {screen < 4 && <button type="button" disabled={!canContinue} onClick={() => void next()} className="rounded-lg bg-purple-600 px-4 py-2 font-bold disabled:opacity-40">{screen === 3 ? 'Confirm Stock Take' : 'Continue'}</button>}</div>
      </footer>
    </div>
  </div>;
};
