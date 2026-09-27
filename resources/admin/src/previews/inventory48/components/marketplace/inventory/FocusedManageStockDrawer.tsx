import React, { useEffect, useMemo, useState } from 'react';
import { ArrowLeft, ArrowRight, Boxes, ChevronRight, ClipboardCheck, History, RefreshCw, X } from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { storeInventoryApi } from '../../../../../integration/storeInventory';
import { MarketplaceLogo } from '../MarketplaceLogo';
import { ManageStockDrawer } from './ManageStockDrawer';
import { StockTakeRecoveryFlow } from './StockTakeRecoveryFlow';
import { warehouseDisplayCode } from './warehouseDisplay';

type Balance = { physical: number | null; reserved: number | null; available: number | null; counted: boolean };
type Facility = Balance & { id: string; name: string; providerId: string; inventoryWarehouseId: number | null };
type StoreRow = {
  id: string; name: string; platform: string; environment?: string; topology: string | null; status: string;
  balance: Balance | null; facilities: Facility[]; error?: string;
};

interface Props {
  mode: 'shared' | 'independent';
  isOpen: boolean;
  onClose: () => void;
  item: MarketplaceInventoryItem | null;
  stores?: any[];
  storeBalances?: any;
  initialStoreId?: string;
  initialWarehouseId?: string;
  initialActionsOpen?: boolean;
  onSelectFirstCount?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onSelectAllocateStock?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onSelectIntake?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onSelectAdjustment?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onSelectTransfer?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onViewLedger?: (item: MarketplaceInventoryItem, storeId?: string, warehouseId?: string) => void;
  onNavigateToMapping?: () => void;
}

const number = (value: number | null | undefined, counted = true) => counted && value !== null && value !== undefined ? value.toLocaleString() : 'Not counted';

function StockSummary({ balance }: { balance: Balance | null }) {
  const entries = [
    ['Physical stock', balance?.physical, 'text-white'],
    ['Reserved for orders', balance?.reserved, 'text-amber-400'],
    ['Available to sell', balance?.available, 'text-emerald-400'],
  ] as const;
  return <div className="grid grid-cols-3 gap-2 rounded-xl border border-neutral-800 bg-neutral-950 px-3 py-4">
    {entries.map(([label, value, color]) => <div key={label} className="flex min-w-0 flex-col items-center gap-2 text-center">
      <span className="text-[10px] leading-snug text-neutral-400 sm:text-xs">{label}</span>
      <strong className={`font-mono text-sm sm:text-lg ${color}`}>{number(value, balance?.counted)}</strong>
    </div>)}
  </div>;
}

function CompactBalances({ balance }: { balance: Balance | null }) {
  return <span className="grid shrink-0 grid-cols-3 gap-2 text-center sm:gap-3">
    {([['Physical', balance?.physical], ['Reserved', balance?.reserved], ['Available', balance?.available]] as const)
      .map(([label, value]) => <span key={label} className="flex min-w-[44px] flex-col items-center gap-1 sm:min-w-[58px]">
        <span className="text-[9px] text-neutral-400 sm:text-[10px]">{label}</span>
        <strong className={`font-mono text-xs ${label === 'Available' ? 'text-emerald-400' : 'text-white'}`}>{number(value, balance?.counted)}</strong>
      </span>)}
  </span>;
}

function ProductIdentity({ item }: { item: MarketplaceInventoryItem }) {
  return <div className="flex items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-800/70 p-3">
    <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-neutral-700">
      {item.imageUrl ? <img src={item.imageUrl} alt={item.productName} className="h-full w-full object-cover" /> : <Boxes className="h-5 w-5 text-neutral-400" />}
    </div>
    <div className="min-w-0"><div className="truncate text-sm font-bold text-white">{item.masterSku}</div>
      <div className="truncate text-xs text-neutral-400">{item.productName}{item.variantName ? ` · ${item.variantName}` : ''}</div>
    </div>
  </div>;
}

const FocusedIndependentDrawer: React.FC<Props> = ({ isOpen, onClose, item, stores, storeBalances,
  initialStoreId, initialWarehouseId, initialActionsOpen = false,
  onSelectFirstCount, onSelectIntake, onSelectAdjustment, onSelectTransfer, onViewLedger, onNavigateToMapping }) => {
  const [rows, setRows] = useState<StoreRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [storeId, setStoreId] = useState<string | null>(null);
  const [facilityId, setFacilityId] = useState<string | null>(null);
  const [actionsOpen, setActionsOpen] = useState(false);
  const [sync, setSync] = useState<any>(null);
  const [syncBusy, setSyncBusy] = useState(false);
  const [syncError, setSyncError] = useState('');
  const [confirmResync, setConfirmResync] = useState(false);
  const [explicitQuantity, setExplicitQuantity] = useState('');
  const [explicitReason, setExplicitReason] = useState('');
  const [confirmExplicit, setConfirmExplicit] = useState(false);
  const [explicitResult, setExplicitResult] = useState<any>(null);
  const [acceptance, setAcceptance] = useState<any>(null);
  const [recovering, setRecovering] = useState(false);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    if (!isOpen || !item) return;
    setStoreId(initialStoreId || null); setFacilityId(initialWarehouseId || null); setActionsOpen(initialActionsOpen);
    setSync(null); setSyncError(''); setAcceptance(null); setRecovering(false);
    setExplicitQuantity(''); setExplicitReason(''); setConfirmExplicit(false); setExplicitResult(null);
  }, [isOpen, item?.id, initialStoreId, initialWarehouseId, initialActionsOpen]);

  useEffect(() => {
    if (!isOpen || !item) return;
    let active = true;
    setLoading(true);
    const mappedIds = new Set(((item as any).mappedStoreIds || []).map(String));
    const eligible = (stores || []).filter((store: any) => !store.isComingSoon &&
      (store.platform === 'webstore' || mappedIds.has(String(store.id))));
    Promise.all(eligible.map(async (store: any): Promise<StoreRow> => {
      const id = String(store.id);
      const base = { id, name: store.nickname || store.name, platform: store.platform, environment: store.environment };
      if (store.platform === 'webstore') {
        const balance = storeBalances?.[id];
        return { ...base, topology: 'no_warehouse', status: 'verified', facilities: [],
          balance: { physical: balance?.physicalStock ?? null, reserved: balance?.reservedStock ?? null,
            available: balance?.availableStock ?? null, counted: !!balance?.isCounted } };
      }
      try {
        const result = await storeInventoryApi.facilityBalances(id, (item as any).variantId);
        if (result.status === 'verified' && result.topology === 'no_warehouse') {
          const balance = storeBalances?.[id];
          return { ...base, topology: result.topology, status: result.status, facilities: [],
            balance: { physical: balance?.physicalStock ?? null, reserved: balance?.reservedStock ?? null,
              available: balance?.availableStock ?? null, counted: !!balance?.isCounted } };
        }
        const facilities: Facility[] = (result.facilities || []).map((facility: any) => ({
          id: String(facility.id), name: String(facility.name), providerId: String(facility.provider_id),
          inventoryWarehouseId: facility.inventory_warehouse_id ?? null,
          physical: facility.physical ?? null, reserved: facility.reserved ?? null,
          available: facility.available ?? null, counted: facility.counted === true,
        }));
        const counted = facilities.length > 0 && facilities.every(facility => facility.counted);
        const balance: Balance = {
          physical: counted ? facilities.reduce((sum, facility) => sum + (facility.physical || 0), 0) : null,
          reserved: counted ? facilities.reduce((sum, facility) => sum + (facility.reserved || 0), 0) : null,
          available: counted ? facilities.reduce((sum, facility) => sum + (facility.available || 0), 0) : null,
          counted,
        };
        return { ...base, topology: result.topology ?? null, status: result.status, facilities, balance,
          error: result.error_code || undefined };
      } catch (error: any) {
        return { ...base, topology: null, status: 'verification_pending', facilities: [], balance: null,
          error: error?.message || 'Warehouse information is unavailable.' };
      }
    })).then(result => { if (active) { setRows(result); setLoading(false); } });
    return () => { active = false; };
  }, [isOpen, item?.id, (item as any)?.variantId, stores, storeBalances, reload]);

  const selectedStore = rows.find(row => row.id === storeId) || null;
  const selectedFacility = selectedStore?.facilities.find(facility => facility.id === facilityId) || null;
  const direct = selectedStore?.topology === 'no_warehouse';
  const atLocation = !!selectedStore && (direct || !!selectedFacility);
  const activeBalance = direct ? selectedStore?.balance || null : selectedFacility || null;
  const locationName = direct ? selectedStore?.name : selectedFacility?.name;
  const acceptanceFacility = acceptance?.facilities?.find((facility: any) => String(facility.id) === selectedFacility?.id);
  const requiresTestAcceptance = selectedStore?.platform === 'lazada' && selectedStore.environment !== 'production';
  const acceptanceReady = !requiresTestAcceptance || (acceptance !== null
    && (acceptance.test_acceptance_enabled !== true
      || (acceptance.eligible === true && acceptanceFacility?.eligible === true)));
  const canRecover = requiresTestAcceptance && !!selectedFacility && acceptance?.test_acceptance_enabled === true
    && acceptance?.eligible === false;
  const actionable = selectedStore?.status === 'verified' && (direct || !!selectedFacility?.inventoryWarehouseId)
    && acceptanceReady;
  const awaitingSync = sync?.status === 'awaiting_confirmation';
  const failedSync = sync?.status === 'sync_failed';
  const checkedMismatch = failedSync && sync?.marketplace_reported_stock !== null
    && sync?.marketplace_reported_stock !== undefined && sync?.difference !== null
    && sync?.difference !== undefined && sync.difference !== 0;
  const mayManage = actionable && !awaitingSync;
  const title = !selectedStore ? 'Choose a store' : !atLocation ? selectedStore.name : actionsOpen ? 'Manage this stock' : locationName || 'Stock location';
  const description = !selectedStore ? `Select the store whose ${item?.masterSku || 'SKU'} stock you want to manage.`
    : !atLocation ? 'Choose a warehouse to view and manage its independent stock.'
      : actionsOpen ? 'Choose an action for this stock location.' : `Manage ${item?.masterSku || 'this SKU'} at this stock location only.`;

  useEffect(() => {
    if (!isOpen || !item || !selectedStore || !selectedFacility || !requiresTestAcceptance) {
      setAcceptance(null); return;
    }
    let active = true;
    setAcceptance(null);
    storeInventoryApi.testFacilityAcceptance(selectedStore.id, (item as any).variantId, Number(selectedFacility.id))
      .then(result => { if (active) setAcceptance(result); })
      .catch(() => { if (active) setAcceptance({ test_acceptance_enabled: true, eligible: false }); });
    return () => { active = false; };
  }, [isOpen, item?.id, storeId, facilityId, requiresTestAcceptance, reload]);

  useEffect(() => {
    if (!isOpen || !item || !selectedStore || !atLocation || selectedStore.platform === 'webstore' || selectedStore.status !== 'verified') {
      setSync(null); setSyncError(''); return;
    }
    let active = true;
    setSync(null); setSyncError('');
    const request = direct ? storeInventoryApi.syncDetails(selectedStore.id, (item as any).variantId)
      : storeInventoryApi.facilitySyncDetails(selectedStore.id, Number(selectedFacility!.id), (item as any).variantId);
    request.then(value => { if (active) setSync(value); })
      .catch(() => { if (active) setSyncError('Stock sync status is unavailable.'); });
    return () => { active = false; };
  }, [isOpen, item?.id, storeId, facilityId, atLocation, reload]);

  const back = () => {
    if (actionsOpen) { setActionsOpen(false); return; }
    if (selectedFacility) { setFacilityId(null); return; }
    if (selectedStore) { setStoreId(null); return; }
    onClose();
  };
  const openStore = (row: StoreRow) => {
    setStoreId(row.id); setFacilityId(null); setActionsOpen(false);
  };
  const openFacility = (facility: Facility) => { setFacilityId(facility.id); setActionsOpen(false); };
  const syncAction = async (resend: boolean) => {
    if (!item || !selectedStore || selectedStore.platform === 'webstore') return;
    setSyncBusy(true); setSyncError('');
    try {
      const result = selectedFacility
        ? resend ? await storeInventoryApi.facilityResync(selectedStore.id, Number(selectedFacility.id), (item as any).variantId, crypto.randomUUID())
          : await storeInventoryApi.facilitySyncCheck(selectedStore.id, Number(selectedFacility.id), (item as any).variantId)
        : resend ? await storeInventoryApi.resyncStock(selectedStore.id, (item as any).variantId, crypto.randomUUID())
          : await storeInventoryApi.checkStock(selectedStore.id, (item as any).variantId);
      setSync(result);
      setConfirmResync(false);
      if (!resend && selectedFacility && requiresTestAcceptance) {
        const next = await storeInventoryApi.testFacilityAcceptance(selectedStore.id, (item as any).variantId, Number(selectedFacility.id));
        setAcceptance(next);
      }
    } catch { setSyncError('Stock sync could not be completed. Please try again.'); }
    finally { setSyncBusy(false); }
  };
  const pushExplicit = async () => {
    if (!item || !selectedStore || !selectedFacility) return;
    setSyncBusy(true); setSyncError('');
    try {
      const result = await storeInventoryApi.pushExplicitFacilityStock(selectedStore.id,
        Number(selectedFacility.id), (item as any).variantId, Number(explicitQuantity),
        explicitReason.trim(), crypto.randomUUID());
      setExplicitResult(result); setConfirmExplicit(false);
      setSync(await storeInventoryApi.facilitySyncDetails(selectedStore.id,
        Number(selectedFacility.id), (item as any).variantId));
    } catch (error: any) { setSyncError(error.message || 'The stock push could not be queued.'); }
    finally { setSyncBusy(false); }
  };
  if (!isOpen || !item) return null;
  const targetWarehouseId = direct ? `direct:${selectedStore?.id}` : selectedFacility?.id;
  const proceed = (action: 'count' | 'intake' | 'adjustment' | 'transfer') => {
    if (!selectedStore || !actionable) return;
    if (action === 'count') onSelectFirstCount?.(item, selectedStore.id, targetWarehouseId);
    if (action === 'intake') onSelectIntake?.(item, selectedStore.id, targetWarehouseId);
    if (action === 'adjustment') onSelectAdjustment?.(item, selectedStore.id, targetWarehouseId);
    if (action === 'transfer') onSelectTransfer?.(item, selectedStore.id, targetWarehouseId);
  };
  return <><div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-3 text-neutral-100 backdrop-blur-sm sm:p-6">
    <div className="fixed inset-0" onClick={onClose} aria-hidden="true" />
    <div role="dialog" aria-modal="true" aria-label="Manage Stock" className="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-neutral-700 bg-neutral-900 shadow-2xl">
      <header className="flex items-start justify-between gap-4 border-b border-neutral-800 px-5 py-5 sm:px-6">
        <div><div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-purple-300">Manage Stock · Independent mode</div>
          <h2 className="text-lg font-bold text-white">{title}</h2><p className="mt-1 text-xs text-neutral-400">{description}</p></div>
        <button type="button" aria-label="Close" onClick={onClose} className="rounded p-1 text-neutral-400 hover:text-white"><X className="h-5 w-5" /></button>
      </header>
      <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
        {selectedStore && <button type="button" onClick={back} className="flex items-center gap-1 text-xs text-purple-300 hover:text-purple-200"><ArrowLeft className="h-3 w-3" />{selectedFacility || actionsOpen ? `Back to ${selectedStore.name}` : 'All stores'}</button>}
        <ProductIdentity item={item} />
        {!selectedStore && <>
          <p className="text-xs font-semibold text-neutral-300">Mapped stores · {rows.length}</p>
          {loading && <p className="text-xs text-neutral-400">Loading stock locations…</p>}
          {!loading && rows.length === 0 && <div className="rounded-xl border border-neutral-800 p-6 text-center text-sm text-neutral-400">
            No mapped stores for this SKU. {onNavigateToMapping && <button onClick={onNavigateToMapping} className="ml-2 text-purple-300 underline">Go to SKU Mapping</button>}
          </div>}
          {rows.map(row => <button key={row.id} type="button" onClick={() => openStore(row)} className="flex w-full flex-col gap-3 rounded-xl border border-neutral-700 bg-neutral-800/60 p-4 text-left hover:border-purple-500 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-center gap-3"><MarketplaceLogo platform={row.platform as any} size="sm" />
              <div className="min-w-0"><strong className="block truncate text-xs text-white">{row.name}</strong><span className="text-[11px] text-neutral-400">{row.topology === 'no_warehouse' ? 'No platform warehouse' : row.status !== 'verified' ? 'Warehouse verification pending' : `${row.facilities.length} platform warehouse${row.facilities.length === 1 ? '' : 's'}`}</span></div></div>
            <div className="flex w-full shrink-0 items-center justify-between gap-2 sm:w-auto"><CompactBalances balance={row.balance} /><ChevronRight className="h-4 w-4 text-neutral-400" /></div>
          </button>)}
        </>}
        {selectedStore && !atLocation && <>
          <StockSummary balance={selectedStore.balance} />
          {selectedStore.status !== 'verified' && <div role="status" className="rounded-xl border border-amber-800 bg-amber-950/30 p-3 text-xs text-amber-300">Warehouse verification is pending. Stock changes are unavailable until it completes.</div>}
          <p className="text-xs font-semibold text-neutral-300">Platform warehouses · {selectedStore.facilities.length}</p>
          {selectedStore.facilities.map(facility => <button key={facility.id} type="button" onClick={() => openFacility(facility)} className="flex w-full flex-col gap-3 rounded-xl border border-neutral-700 bg-neutral-800/60 p-4 text-left hover:border-purple-500 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0"><strong className="block truncate text-xs text-white">{facility.name}</strong><span className="font-mono text-[10px] text-neutral-400">{warehouseDisplayCode(facility.providerId)}</span></div>
            <div className="flex w-full shrink-0 items-center justify-between gap-2 sm:w-auto"><CompactBalances balance={facility} /><ChevronRight className="h-4 w-4 text-neutral-400" /></div>
          </button>)}
        </>}
        {atLocation && !actionsOpen && <>
          <p className="text-xs font-semibold text-neutral-300">{selectedStore!.name} · {direct ? 'Store ledger' : `Platform warehouse ${warehouseDisplayCode(selectedFacility!.providerId)}`}</p>
          <StockSummary balance={activeBalance} />
          {selectedFacility && selectedStore?.platform === 'lazada' && <div className="rounded-xl border border-purple-800 bg-purple-950/20 p-4 text-xs">
            <strong className="block text-sm">Push stock to this warehouse</strong>
            <p className="mt-2 text-neutral-300">Send an explicit quantity to the selected marketplace warehouse. JW stock and order reservations stay unchanged.</p>
            <label className="mt-3 block font-semibold">Quantity to push<input type="number" min="0" max="2147483647" step="1" value={explicitQuantity}
              onChange={event => setExplicitQuantity(event.target.value)} className="mt-2 w-full rounded-lg border border-neutral-600 bg-neutral-800 p-3" /></label>
            <label className="mt-3 block font-semibold">Reason<textarea rows={2} value={explicitReason}
              onChange={event => setExplicitReason(event.target.value)} placeholder="Why are you pushing this quantity?"
              className="mt-2 w-full rounded-lg border border-neutral-600 bg-neutral-800 p-3" /></label>
            {!confirmExplicit ? <button type="button" disabled={!/^\d+$/.test(explicitQuantity) || Number(explicitQuantity) > 2147483647 || explicitReason.trim().length < 10 || syncBusy}
              onClick={() => setConfirmExplicit(true)} className="mt-3 rounded-lg bg-purple-600 px-4 py-2 font-bold disabled:opacity-40">Push stock now</button>
              : <div className="mt-3 rounded-lg border border-purple-700 p-3"><p>Push {explicitQuantity} units to {selectedStore.name} · {selectedFacility.name}?</p>
                <div className="mt-3 flex gap-2"><button type="button" onClick={() => setConfirmExplicit(false)} className="rounded border border-neutral-600 px-3 py-2">Cancel</button>
                  <button type="button" disabled={syncBusy} onClick={() => void pushExplicit()} className="rounded bg-purple-600 px-3 py-2 font-bold disabled:opacity-40">Confirm push</button></div></div>}
            {explicitResult && <p role="status" className="mt-3 text-emerald-300">Push queued for {explicitResult.target_quantity} units. Check status below for provider confirmation.</p>}
          </div>}
          {!actionable && !awaitingSync && !failedSync && <div role="status" className="rounded-xl border border-amber-800 bg-amber-950/30 p-3 text-xs text-amber-300">{selectedStore!.status !== 'verified' ? 'Warehouse verification is pending.' : !acceptanceReady ? 'JW ledger actions need review. Marketplace stock can still be pushed above.' : 'This warehouse does not yet have an active stock ledger.'}</div>}
          {canRecover && !awaitingSync && !failedSync && <button type="button" onClick={() => setRecovering(true)} className="w-full rounded-xl border border-purple-700 bg-purple-950/30 p-4 text-left text-xs font-bold text-purple-200 hover:border-purple-400">Resolve stock issue <ArrowRight className="ml-2 inline h-4 w-4" /></button>}
          {selectedStore!.platform !== 'webstore' && <div role="status" className={`rounded-xl border p-4 text-xs ${sync?.status === 'synced' ? 'border-emerald-800 bg-emerald-950/40' : failedSync ? 'border-rose-800 bg-rose-950/30' : awaitingSync ? 'border-amber-800 bg-amber-950/30' : 'border-neutral-700 bg-neutral-800/60'}`}>
            <strong className={sync?.status === 'synced' ? 'text-emerald-300' : failedSync ? 'text-rose-300' : awaitingSync ? 'text-amber-300' : 'text-white'}>{sync?.status === 'synced' ? 'Marketplace stock confirmed' : failedSync ? 'Marketplace sync failed' : awaitingSync ? 'Marketplace result needs confirmation' : 'Marketplace stock sync'}</strong>
            <p className="mt-2 text-neutral-300">{sync?.status === 'synced' ? 'The marketplace readback matches JW available stock for this location.' : checkedMismatch ? `JW has ${sync.jw_available_stock} available; the marketplace reports ${sync.marketplace_reported_stock}. Review this difference before resyncing.` : failedSync ? 'The previous stock update failed. JW stock remains available to manage; check the exact marketplace location before resending.' : awaitingSync ? 'The previous update may have reached the marketplace. Check this exact location before sending another update.' : 'Check the exact marketplace location to confirm its stock.'}</p>
            {syncError && <p role="alert" className="mt-2 text-rose-300">{syncError}</p>}
            {sync?.can_resync && <button type="button" onClick={() => setConfirmResync(true)} className="mt-3 rounded-lg border border-purple-600 px-3 py-2 font-semibold text-purple-200">Resend current stock</button>}
            {confirmResync && <div className="mt-3 rounded-lg border border-purple-700 bg-purple-950/30 p-3"><p>Send JW available stock ({sync?.jw_available_stock}) to this exact marketplace location?</p><div className="mt-3 flex gap-2"><button type="button" onClick={() => setConfirmResync(false)} className="rounded border border-neutral-600 px-3 py-2">Cancel</button><button type="button" disabled={syncBusy} onClick={() => void syncAction(true)} className="rounded bg-purple-600 px-3 py-2 font-bold disabled:opacity-40">Confirm resync</button></div></div>}
          </div>}
        </>}
        {atLocation && actionsOpen && <div className="space-y-3">
          {!actionable ? <><p className="text-xs text-amber-300">Stock actions are unavailable for this location.</p>
              {canRecover && <button type="button" onClick={() => setRecovering(true)} className="w-full rounded-xl border border-purple-700 bg-purple-950/30 p-4 text-left text-xs font-bold text-purple-200 hover:border-purple-400">Resolve stock issue <ArrowRight className="ml-2 inline h-4 w-4" /></button>}</>
            : !activeBalance?.counted ? <button type="button" onClick={() => proceed('count')} className="flex w-full items-center gap-2 rounded-xl bg-purple-600 p-4 text-left text-sm font-bold hover:bg-purple-500"><ClipboardCheck className="h-4 w-4" />First Count this location<ArrowRight className="ml-auto h-4 w-4" /></button>
              : <><button type="button" onClick={() => proceed('intake')} className="block w-full rounded-xl border border-neutral-700 bg-neutral-800 p-4 text-left text-sm hover:border-purple-500">Actual Stock Intake</button>
                <button type="button" onClick={() => proceed('adjustment')} className="block w-full rounded-xl border border-neutral-700 bg-neutral-800 p-4 text-left text-sm hover:border-purple-500">Stock Audit Adjustment</button>
                {activeBalance.available !== null && activeBalance.available > 0 && <button type="button" onClick={() => proceed('transfer')} className="block w-full rounded-xl border border-neutral-700 bg-neutral-800 p-4 text-left text-sm hover:border-purple-500">Transfer Stock</button>}</>}
        </div>}
      </div>
      <footer className="flex items-center justify-between gap-3 border-t border-neutral-800 bg-neutral-950/70 px-5 py-4 sm:px-6">
        <button type="button" onClick={back} className="rounded-lg border border-neutral-700 px-4 py-2 text-xs text-neutral-300 hover:bg-neutral-800">{selectedStore ? 'Back' : 'Close'}</button>
        {atLocation && !actionsOpen && <div className="flex gap-2">{selectedStore?.platform !== 'webstore' && sync?.can_check && (failedSync || awaitingSync || sync?.status === 'not_synced') && <button type="button" disabled={syncBusy} onClick={() => void syncAction(false)} className={`rounded-lg px-4 py-2 text-xs font-bold disabled:opacity-40 ${awaitingSync ? 'bg-purple-600 text-white' : 'border border-neutral-600 text-neutral-200'}`}>Check status</button>}
          {!awaitingSync && <button type="button" disabled={!mayManage} onClick={() => setActionsOpen(true)} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white hover:bg-purple-500 disabled:opacity-40">Manage this stock</button>}</div>}
        {atLocation && actionsOpen && onViewLedger && <button type="button" onClick={() => onViewLedger(item, selectedStore!.id, targetWarehouseId)} className="flex items-center gap-1 rounded-lg border border-neutral-700 px-4 py-2 text-xs text-neutral-300"><History className="h-3 w-3" />View ledger</button>}
      </footer>
    </div>
  </div>
    {recovering && item && selectedStore && selectedFacility && <StockTakeRecoveryFlow item={item}
      store={{ id: selectedStore.id, name: selectedStore.name }} facility={selectedFacility}
      onClose={() => setRecovering(false)} onSaved={() => { setReload(value => value + 1); setAcceptance(null); }} />}
  </>;
};

export const FocusedManageStockDrawer: React.FC<Props> = props => props.mode === 'shared'
  ? <ManageStockDrawer {...props} />
  : <FocusedIndependentDrawer {...props} />;
