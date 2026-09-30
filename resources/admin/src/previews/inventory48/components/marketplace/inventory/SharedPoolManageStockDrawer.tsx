import React, { useEffect, useState } from 'react';
import { ArrowLeft, Boxes, ChevronRight, X } from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { storeInventoryApi } from '../../../../../integration/storeInventory';

type Step = 'home' | 'actions' | 'count' | 'review' | 'complete' | 'intake' | 'audit' | 'ledger';
type Movement = { id: number; delta: number; current_after: number; reason: string; created_at: string };
type MarketplaceStoreRead = { store_id:number; name:string; platform:string; connected:boolean; takeover_enabled:boolean; warehouse_mode?:boolean; status:'read'|'unavailable'; quantity:number|null; warehouses:Array<{id:number;name:string}>; reason?:string };
type MarketplaceRead = { stores:MarketplaceStoreRead[]; available_total:number; all_read:boolean };
type SyncStage = {store_id:number|null;facility_id?:number|null;status:string;target_quantity:number|null};

interface Props {
  isOpen: boolean;
  item: MarketplaceInventoryItem | null;
  initialActionsOpen?: boolean;
  onClose: () => void;
  onFirstCount: (item: MarketplaceInventoryItem, quantity: number) => Promise<{operation_id:string;external_stages?:SyncStage[]}>;
  onSelectStockAction?: (item: MarketplaceInventoryItem, action:'intake'|'audit') => void;
}

export const SharedPoolManageStockDrawer: React.FC<Props> = ({ isOpen, item, initialActionsOpen = false, onClose, onFirstCount, onSelectStockAction }) => {
  const [step, setStep] = useState<Step>('home');
  const [quantity, setQuantity] = useState('');
  const [completedStock, setCompletedStock] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [ledger, setLedger] = useState<{ current_stock: number | null; movements: Movement[] } | null>(null);
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [marketplaceRead, setMarketplaceRead] = useState<MarketplaceRead | null>(null);
  const [marketplaceLoading, setMarketplaceLoading] = useState(false);
  const [marketplaceError, setMarketplaceError] = useState('');
  const [operationId, setOperationId] = useState<string | null>(null);
  const [syncStages, setSyncStages] = useState<SyncStage[]>([]);

  useEffect(() => {
    if (!isOpen) return;
    setStep(initialActionsOpen && item?.currentStock != null ? 'actions' : 'home');
    setQuantity(''); setCompletedStock(null); setSaving(false); setError(''); setLedger(null);
    setMarketplaceRead(null); setMarketplaceLoading(false); setMarketplaceError(''); setOperationId(null); setSyncStages([]);
  }, [isOpen, item?.id, initialActionsOpen]);

  useEffect(() => {
    if (!isOpen || step !== 'count' || !item?.variantId) return;
    let active = true;
    setMarketplaceLoading(true); setMarketplaceError('');
    storeInventoryApi.sharedFirstCountMarketplaceStock(item.variantId)
      .then((result: MarketplaceRead) => { if (active) setMarketplaceRead(result); })
      .catch((cause: Error) => { if (active) setMarketplaceError(cause.message || 'Marketplace stock could not be read.'); })
      .finally(() => { if (active) setMarketplaceLoading(false); });
    return () => { active = false; };
  }, [isOpen, item?.variantId, step]);

  useEffect(() => {
    if (!isOpen || step !== 'complete' || !operationId) return;
    let active = true;
    const refresh = () => storeInventoryApi.operation(operationId)
      .then((result: {external_stages:SyncStage[]}) => { if (active) setSyncStages(result.external_stages || []); })
      .catch(() => {});
    void refresh();
    const timer = window.setInterval(() => void refresh(), 5000);
    return () => { active = false; window.clearInterval(timer); };
  }, [isOpen, step, operationId]);

  useEffect(() => {
    if (!isOpen || step !== 'ledger' || !item) return;
    let active = true;
    setLedgerLoading(true); setError('');
    storeInventoryApi.sharedLedger(item.variantId!)
      .then(result => { if (active) setLedger(result); })
      .catch((cause: Error) => { if (active) setError(cause.message || 'The JW ledger is unavailable.'); })
      .finally(() => { if (active) setLedgerLoading(false); });
    return () => { active = false; };
  }, [isOpen, item?.id, step]);

  if (!isOpen || !item) return null;
  const currentStock = completedStock ?? item.currentStock;
  const counted = currentStock !== null && currentStock !== undefined;
  const validQuantity = /^(0|[1-9]\d*)$/.test(quantity) && Number.isSafeInteger(Number(quantity)) && Number(quantity) <= 2147483647;
  const setPage = (next: Step) => { setError(''); setStep(next); };
  const back = () => {
    if (step === 'home') { onClose(); return; }
    if (step === 'review') { setPage('count'); return; }
    if (step === 'intake' || step === 'audit' || step === 'ledger') { setPage('actions'); return; }
    setPage('home');
  };
  const confirmCount = async () => {
    if (!validQuantity || saving) return;
    setSaving(true); setError('');
    try {
      const receipt = await onFirstCount(item, Number(quantity));
      setOperationId(receipt.operation_id);
      setSyncStages(receipt.external_stages || []);
      setCompletedStock(Number(quantity));
      setStep('complete');
    } catch (cause: any) {
      setError(cause?.message || 'First Count could not be saved. Review the latest stock and try again.');
    } finally { setSaving(false); }
  };
  const pageTitle: Record<Step, string> = {
    home: 'Manage shared stock', actions: 'Manage this stock', count: 'First Count this SKU',
    review: 'Review First Count', complete: 'First Count recorded',
    intake: 'Actual Stock Intake', audit: 'Stock Audit Adjustment', ledger: 'JW stock ledger',
  };
  const pageDescription: Record<Step, string> = {
    home: 'One JW Current Stock ledger for this SKU across connected channels.',
    actions: 'Choose an action for the one shared SKU balance.',
    count: 'Set the opening absolute JW Current Stock for the shared pool.',
    review: 'Confirm the opening balance before creating the JW stock ledger.',
    complete: 'This SKU now has one counted JW Current Stock balance.',
    intake: 'Shared Pool · one SKU ledger', audit: 'Shared Pool · one SKU ledger', ledger: 'Shared Pool · one SKU ledger',
  };
  const identity = <div className="flex items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-800/70 p-3">
    <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-neutral-700">
      {item.imageUrl ? <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" /> : <Boxes className="h-5 w-5 text-neutral-400" />}
    </div>
    <div className="min-w-0"><div className="truncate text-sm font-bold text-white">{item.masterSku}</div>
      <div className="truncate text-xs text-neutral-400">{item.productName}{item.variantName ? ` · ${item.variantName}` : ''}</div></div>
  </div>;
  const summary = <div className="rounded-xl border border-neutral-700 bg-neutral-950 p-5 text-center">
    <div className="text-xs text-neutral-400">JW Current Stock · Shared Pool</div>
    <strong className={`mt-2 block font-mono ${counted ? 'text-xl text-emerald-400' : 'text-base text-amber-400'}`}>{counted ? Number(currentStock).toLocaleString() : 'Not counted'}</strong>
    <p className="mt-2 text-[11px] text-neutral-500">{counted ? 'One SKU balance. This is not the sum of marketplace stock.' : 'No JW stock changes or marketplace pushes until First Count is completed.'}</p>
  </div>;
  const choice = (label: string, next: Step, primary = false) => <button type="button" onClick={() => (next === 'intake' || next === 'audit') && onSelectStockAction ? onSelectStockAction(item, next) : setPage(next)}
    className={`flex w-full items-center justify-between rounded-xl border p-4 text-left text-sm font-bold text-white hover:border-purple-500 ${primary ? 'border-purple-600 bg-purple-900' : 'border-neutral-700 bg-neutral-800'}`}>
    <span>{label}</span><ChevronRight className="h-4 w-4 shrink-0 text-neutral-300" />
  </button>;
  const storeRows = (phase: 'count'|'review'|'complete') => <div className="space-y-2">{marketplaceRead?.stores.map(store => {
    const stages = syncStages.filter(entry => entry.store_id === store.store_id);
    const expected = store.warehouses.length || 1;
    const status = phase === 'count' ? store.status === 'read' ? `${store.quantity?.toLocaleString()} units` : 'Unavailable'
      : phase === 'review' ? !store.takeover_enabled ? 'Takeover paused' : !store.connected ? 'Disconnected' : store.warehouse_mode && !store.warehouses.length ? 'No takeover warehouses' : store.warehouses.length > 1 ? `Push to ${store.warehouses.length} warehouses` : 'Push after JW count'
      : !store.takeover_enabled ? 'No push' : store.warehouse_mode && !store.warehouses.length ? 'No takeover warehouses' : stages.length >= expected && stages.every(entry => entry.status === 'confirmed') ? 'Synced' : stages.some(entry => ['failed', 'uncertain', 'auth_expired'].includes(entry.status)) ? 'Needs attention' : stages.some(entry => ['queued', 'processing', 'waiting_source'].includes(entry.status)) ? 'Confirmation pending' : stages.length > 0 && stages.every(entry => ['not_applicable', 'superseded'].includes(entry.status)) ? 'Not pushed' : 'Awaiting sync status';
    return <div key={store.store_id} className="flex items-center justify-between gap-3 rounded-xl border border-neutral-700 bg-neutral-800 p-3 text-xs">
      <div className="min-w-0"><b className="block truncate">{store.name}</b><span className="mt-1 block text-[11px] text-neutral-400">{store.platform} · mapped · {store.takeover_enabled ? 'Takeover on' : 'Paused'}</span>
        {store.warehouses.length > 0 && <span className="mt-1 block text-[11px] text-neutral-400">Takeover warehouses: {store.warehouses.map(warehouse => warehouse.name).join(' · ')}</span>}</div>
      <b className={`shrink-0 text-right ${store.status === 'unavailable' || status === 'Needs attention' ? 'text-rose-300' : 'text-emerald-400'}`}>{status}</b>
    </div>;
  })}</div>;

  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-3 text-neutral-100 backdrop-blur-sm sm:p-6">
    <div className="fixed inset-0" onClick={onClose} aria-hidden="true" />
    <div role="dialog" aria-modal="true" aria-label="Manage Stock" className="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-neutral-700 bg-neutral-900 shadow-2xl">
      <header className="flex items-start justify-between gap-4 border-b border-neutral-800 px-5 py-5 sm:px-6">
        <div><div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-purple-300">Manage Stock · Shared Pool Mode</div>
          <h2 className="text-lg font-bold text-white">{pageTitle[step]}</h2><p className="mt-1 text-xs text-neutral-400">{pageDescription[step]}</p></div>
        <button type="button" aria-label="Close" onClick={onClose} className="rounded p-1 text-neutral-400 hover:text-white"><X className="h-5 w-5" /></button>
      </header>
      <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
        {step !== 'home' && <button type="button" onClick={back} className="flex items-center gap-1 text-xs text-purple-300 hover:text-purple-200"><ArrowLeft className="h-3 w-3" />{step === 'review' ? 'Edit count' : step === 'intake' || step === 'audit' || step === 'ledger' ? 'Back to actions' : 'Back to shared stock'}</button>}
        {identity}
        {step === 'home' && <>
          <p className="text-xs font-semibold text-neutral-300">This SKU · one shared balance</p>{summary}
          {counted ? <div className="rounded-xl border border-purple-800 bg-purple-950/30 p-4 text-xs leading-relaxed text-purple-100"><b className="text-white">Shared Pool rule</b><br />Stock actions change this single JW SKU ledger. Eligible marketplace stores are considered separately for later push, based on mapping, connection and takeover.</div>
            : <><div className="rounded-xl border border-purple-800 bg-purple-950/30 p-4 text-xs leading-relaxed text-purple-100"><b className="text-white">First Count starts the JW stock ledger.</b><br />Count the physical units held for this SKU once. Shopee and Lazada quantities are reference snapshots; do not add them together.</div>{choice('First Count this SKU', 'count', true)}</>}
        </>}
        {step === 'actions' && <>{summary}<div className="space-y-3">{choice('Actual Stock Intake', 'intake')}{choice('Stock Audit Adjustment', 'audit')}{choice('View JW stock ledger', 'ledger')}</div><p className="text-xs text-neutral-400">Stock Transfer is unavailable in Shared Pool mode.</p></>}
        {step === 'count' && <><div className="space-y-2"><div className="flex items-center justify-between"><b className="text-xs">Current marketplace stock · mapped stores ({marketplaceRead?.stores.length ?? '…'})</b><button type="button" disabled={marketplaceLoading} onClick={() => { setMarketplaceRead(null); setMarketplaceLoading(true); setMarketplaceError(''); void storeInventoryApi.sharedFirstCountMarketplaceStock(item.variantId!).then((result: MarketplaceRead) => setMarketplaceRead(result)).catch((cause: Error) => setMarketplaceError(cause.message || 'Marketplace stock could not be read.')).finally(() => setMarketplaceLoading(false)); }} className="text-xs text-purple-300 disabled:opacity-40">Check again</button></div>
          {marketplaceLoading && <p className="text-xs text-neutral-400">Checking mapped marketplace stores…</p>}
          {marketplaceError && <p role="alert" className="text-xs text-rose-300">{marketplaceError}</p>}
          {marketplaceRead && storeRows('count')}
          {marketplaceRead && <p className="rounded-xl border border-amber-800 bg-amber-950/30 p-3 text-xs text-amber-200">Marketplace quantities can mirror the same physical stock. Available subtotal {marketplaceRead.available_total.toLocaleString()} includes successful reads only; unavailable stock is unknown, not zero.</p>}</div>
          <label htmlFor="shared-first-count-quantity" className="block text-xs font-semibold text-neutral-200">Physical units counted for this SKU · JW opening stock</label>
          <input id="shared-first-count-quantity" type="number" min="0" max="2147483647" step="1" inputMode="numeric" value={quantity} onChange={event => setQuantity(event.target.value)} placeholder="Enter quantity, including 0"
            className="w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none" />
          {quantity && !validQuantity && <p role="alert" className="text-xs text-rose-300">Enter a whole number from 0 to 2,147,483,647.</p>}
          <div className="flex flex-wrap gap-2"><button type="button" disabled={!marketplaceRead} onClick={() => setQuantity(String(marketplaceRead?.available_total ?? ''))} className="rounded-lg border border-neutral-700 px-3 py-2 text-xs disabled:opacity-40">Use Marketplace Total</button><button type="button" onClick={() => setQuantity('')} className="rounded-lg border border-neutral-700 px-3 py-2 text-xs">Manual Total</button></div></>}
        {step === 'review' && <><div className="space-y-3 rounded-xl border border-neutral-700 bg-neutral-800 p-4 text-xs">
          <div className="flex justify-between gap-3"><span className="text-neutral-400">SKU</span><b>{item.masterSku}</b></div>
          <div className="flex justify-between gap-3"><span className="text-neutral-400">Ledger</span><b>Shared JW Current Stock</b></div>
          <div className="flex justify-between gap-3"><span className="text-neutral-400">Opening quantity</span><b>{Number(quantity).toLocaleString()}</b></div>
          <div className="flex justify-between gap-3"><span className="text-neutral-400">Marketplace reads</span><b>Reference only</b></div>
        </div><b className="text-xs">After JW Ledger is saved</b>{storeRows('review')}<div className="rounded-xl border border-amber-800 bg-amber-950/30 p-4 text-xs leading-relaxed text-amber-200">The JW First Count is saved once. Provider delivery is tracked separately; a failed push does not undo the JW Ledger.</div></>}
        {step === 'complete' && <><div className="rounded-xl border border-emerald-800 bg-emerald-950/30 p-4 text-xs leading-relaxed text-emerald-200"><b>JW ledger: counted · {Number(completedStock).toLocaleString()} units</b><br />The JW First Count was saved.</div><b className="text-xs">Per-store delivery</b>{storeRows('complete')}{summary}</>}
        {(step === 'intake' || step === 'audit') && <>{summary}<div className="rounded-xl border border-purple-800 bg-purple-950/30 p-4 text-xs leading-relaxed text-purple-100"><b>{pageTitle[step]}</b><br />{step === 'intake' ? 'Received physical units will add to this one shared SKU balance.' : 'A verified physical recount will correct the absolute balance of this one SKU.'}</div><div className="rounded-xl border border-amber-800 bg-amber-950/30 p-4 text-xs text-amber-200">This stock-writing flow will be connected in its own later phase.</div></>}
        {step === 'ledger' && <><p className="text-xs text-neutral-400">Latest 50 JW Current Stock movements for this SKU.</p>
          {ledgerLoading && <p className="text-xs text-neutral-400">Loading JW ledger…</p>}
          {!ledgerLoading && ledger && <>{summary}{ledger.movements.length === 0 ? <p className="rounded-xl border border-neutral-700 p-4 text-xs text-neutral-400">No JW Current Stock movements have been recorded for this SKU.</p>
            : <div className="space-y-2">{ledger.movements.map(movement => <div key={movement.id} className="flex items-start justify-between gap-3 rounded-xl border border-neutral-700 bg-neutral-800 p-3 text-xs"><div><b>{movement.reason}</b><div className="mt-1 text-neutral-400">{new Date(movement.created_at).toLocaleString()}</div></div><div className="text-right font-mono"><b>{movement.current_after}</b><div className={movement.delta >= 0 ? 'text-emerald-400' : 'text-amber-400'}>{movement.delta >= 0 ? '+' : ''}{movement.delta}</div></div></div>)}</div>}</>}
        </>}
        {error && <p role="alert" className="text-xs text-rose-300">{error}</p>}
      </div>
      <footer className="flex items-center justify-between gap-3 border-t border-neutral-800 bg-neutral-950/70 px-5 py-4 sm:px-6">
        <button type="button" onClick={step === 'complete' ? onClose : back} className="rounded-lg border border-neutral-700 px-4 py-2 text-xs text-neutral-300 hover:bg-neutral-800">{step === 'home' || step === 'complete' ? 'Close' : 'Back'}</button>
        {step === 'home' && counted && <button type="button" onClick={() => setPage('actions')} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white hover:bg-purple-500">Manage this stock</button>}
        {step === 'count' && <button type="button" disabled={!validQuantity} onClick={() => setPage('review')} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-40">Review First Count</button>}
        {step === 'review' && <button type="button" disabled={saving} onClick={() => void confirmCount()} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-40">{saving ? 'Saving…' : 'Confirm First Count'}</button>}
        {step === 'complete' && <button type="button" onClick={() => setPage('actions')} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white">Manage this stock</button>}
      </footer>
    </div>
  </div>;
};
