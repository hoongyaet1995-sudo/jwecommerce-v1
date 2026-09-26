import React, { useCallback, useEffect, useState } from 'react';
import { ArrowLeft, Boxes, Check, CheckCircle2, ChevronDown, ClipboardCheck, Lock, X } from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { MarketplaceLogo } from '../MarketplaceLogo';
import { InventoryStoreChannel, StoreStockAccountBalance } from './inventoryTypes';
import { StoreWithWarehouses, SkuWarehouseStockBalance } from './warehouseInventoryTypes';
import { storeInventoryApi } from '../../../../../integration/storeInventory';
import './focused-independent-allocation.css';

type Step = 'input' | 'review' | 'success';

interface Props {
  item: MarketplaceInventoryItem;
  step: Step;
  onClose: () => void;
  onBackToInput: () => void;
  onReview: () => void;
  onConfirm: () => void;
  onNextSku: () => void;
  nextSkuCount: number;
  onOpenStockSyncModal?: (item: MarketplaceInventoryItem, store: InventoryStoreChannel, facilityId?: number) => void;
  activeStores: InventoryStoreChannel[];
  storeConfigs: StoreWithWarehouses[];
  skuStoreBalances: Record<string, StoreStockAccountBalance>;
  warehouseStockData: Record<string, Record<string, SkuWarehouseStockBalance>>;
  physicalCountInput: string;
  onPhysicalCountChange: (value: string) => void;
  isCountLocked: boolean;
  onUnlockCount: () => void;
  allocations: Record<string, string>;
  onAllocationChange: (storeId: string, value: string) => void;
  warehouseAllocations: Record<string, string>;
  onWarehouseAllocationChange: (storeId: string, warehouseId: string, value: string) => void;
  parsedAllocations: Record<string, number>;
  parsedWarehouseAllocations: Record<string, number>;
  providerReferences: Record<string, Record<string, number>>;
  providerReferenceFailures: Record<string, Record<string, string>>;
  providerReferenceLoading: boolean;
  isVerifiedCountValid: boolean;
  verifiedCountNum: number;
  totalAllocatedNum: number;
  remainingCount: number;
  totalReservedAcrossStores: number;
  canProceedToReview: boolean;
  hasIncompleteTopology: boolean;
  hasReservationConflict: boolean;
  hasConnectionBlocker: boolean;
  hasVerificationPendingAttempt: boolean;
  hasInvalidAllocationNumber: boolean;
  hasNegativeAllocation: boolean;
  warehouseReservationConflicts: Record<string, { allocated: number; reserved: number }>;
  storeReservationConflicts: Record<string, { allocated: number; reserved: number }>;
  operator: string;
  reason: string;
  notes: string;
  onNotesChange: (value: string) => void;
  saveError: string;
  onOpenStockTakeReview: () => void;
  isSaving: boolean;
  confirmedCount: number | null;
}

export const FocusedIndependentAllocationModal: React.FC<Props> = props => {
  const [inputView, setInputView] = useState<'count' | 'destinations'>('count');
  const [selectedStoreId, setSelectedStoreId] = useState<string | null>(null);
  const [syncStates, setSyncStates] = useState<Record<string, 'synced' | 'pending' | 'failed' | 'unavailable'>>({});
  const [isCheckingSync, setIsCheckingSync] = useState(false);
  const selectedStore = props.activeStores.find(store => store.id === selectedStoreId);
  const selectedConfig = props.storeConfigs.find(config => config.storeId === selectedStoreId);
  const allocated = props.totalAllocatedNum;
  const remaining = props.remainingCount;
  const isBalanced = props.isVerifiedCountValid && remaining === 0;
  const screen = props.step === 'input' ? inputView : props.step;

  const back = () => {
    if (props.step === 'review') { props.onBackToInput(); setInputView('destinations'); return; }
    if (props.step === 'success') { props.onClose(); return; }
    if (selectedStoreId) { setSelectedStoreId(null); return; }
    if (inputView === 'destinations') { setInputView('count'); return; }
    props.onClose();
  };
  const next = () => {
    if (props.step === 'success') { if (props.nextSkuCount) props.onNextSku(); else props.onClose(); return; }
    if (props.step === 'review') { props.onConfirm(); return; }
    if (inputView === 'count') { if (props.isVerifiedCountValid && props.verifiedCountNum >= props.totalReservedAcrossStores) setInputView('destinations'); return; }
    if (selectedStoreId) { setSelectedStoreId(null); return; }
    props.onReview();
  };
  const balanceMessage = !props.isVerifiedCountValid ? 'Enter a verified count'
    : remaining === 0 ? 'All units allocated'
      : remaining > 0 ? 'Units left to allocate' : 'Allocation exceeds verified total';
  const blocker = props.hasIncompleteTopology ? 'A mapped store is still verifying its warehouses.'
    : props.hasReservationConflict ? 'An allocation is lower than reserved customer orders.'
      : props.hasConnectionBlocker ? 'Reconnect the affected store before allocating stock.'
        : props.hasVerificationPendingAttempt ? 'A warehouse is still being verified.'
          : props.hasInvalidAllocationNumber || props.hasNegativeAllocation ? 'Enter whole numbers of zero or more.'
            : !isBalanced ? 'Allocate the entire verified quantity before review.' : '';
  const storeTotal = (store: InventoryStoreChannel) => props.parsedAllocations[store.id] ?? 0;
  const storeConfig = (store: InventoryStoreChannel) => props.storeConfigs.find(config => config.storeId === store.id);
  const disabledStore = (store: InventoryStoreChannel, config?: StoreWithWarehouses) =>
    !config || (store.platform !== 'webstore' && !config.isSkuMapped)
    || config.warehouseSyncStatus !== 'synced' || store.connectionState === 'expired';
  const balanceClass = !props.isVerifiedCountValid ? '' : remaining === 0 ? 'is-balanced' : remaining < 0 ? 'is-over' : '';
  const variantId = Number((props.item as MarketplaceInventoryItem & { variantId?: number }).variantId);
  const syncTargets = props.activeStores.flatMap(store => {
    if (store.platform === 'webstore') return [];
    const config = props.storeConfigs.find(candidate => candidate.storeId === store.id);
    if (!config?.isSkuMapped) return [];
    return config.warehouses.length
      ? config.warehouses.filter(warehouse => (props.parsedWarehouseAllocations[warehouse.id] ?? 0) > 0 && /^\d+$/.test(warehouse.id))
          .map(warehouse => ({ key: `${store.id}:${warehouse.id}`, store, warehouse }))
      : (props.parsedAllocations[store.id] ?? 0) > 0 ? [{ key: `${store.id}:direct`, store, warehouse: null }] : [];
  });
  const checkSync = useCallback(async (targets: typeof syncTargets, active: () => boolean) => {
    if (!targets.length) return;
    setIsCheckingSync(true);
    const results = await Promise.all(targets.map(async target => {
      try {
        const detail = target.warehouse
          ? await storeInventoryApi.facilitySyncCheck(target.store.id, Number(target.warehouse.id), variantId)
          : await storeInventoryApi.checkStock(target.store.id, variantId);
        const status = detail?.status;
        return [target.key, status === 'synced' ? 'synced' : ['failed', 'sync_failed', 'auth_expired'].includes(status) ? 'failed' : 'pending'] as const;
      } catch {
        return [target.key, 'unavailable'] as const;
      }
    }));
    if (active()) { setSyncStates(Object.fromEntries(results)); setIsCheckingSync(false); }
  }, [variantId]);
  useEffect(() => {
    if (props.step !== 'success') return;
    let active = true;
    void checkSync(syncTargets, () => active);
    return () => { active = false; };
  // The confirmed allocation is fixed at this point. Refresh is explicit to avoid repeated provider calls on rerenders.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.step]);

  return <div className="allocation-focus-backdrop" role="presentation">
    <div className="allocation-focus" role="dialog" aria-modal="true" aria-label="Allocate Stock">
      <header className="allocation-focus-header">
        <div><h2>Allocate Stock</h2><p>Independent mode · Allocate one verified SKU total across its mapped stock locations.</p></div>
        <button type="button" aria-label="Close allocation" onClick={props.onClose}><X size={18} /></button>
      </header>
      <div className="allocation-focus-progress"><span style={{ width: props.step === 'success' ? '100%' : props.step === 'review' ? '100%' : inputView === 'destinations' ? '67%' : '33%' }} /></div>
      <div className="allocation-focus-body">
        {selectedStoreId && props.step === 'input' && <button type="button" className="allocation-focus-backlink" onClick={() => setSelectedStoreId(null)}><ArrowLeft size={13} /> All stores</button>}
        <div className="allocation-focus-product">
          <div className="allocation-focus-photo">{props.item.imageUrl ? <img src={props.item.imageUrl} alt={props.item.productName} /> : <Boxes size={21} />}</div>
          <div><strong>{props.item.masterSku}</strong><small>{props.item.productName}{props.item.variantName ? ` · ${props.item.variantName}` : ''}</small></div>
        </div>

        {props.step === 'input' && inputView === 'count' && <>
          <p className="allocation-focus-kicker">Step 1 · Verified total</p>
          <h3>How many units were counted?</h3>
          <p className="allocation-focus-intro">Use the approved physical count for this SKU. Marketplace quantities are reference only.</p>
          <div className="allocation-focus-count">
            <div className="allocation-focus-count-top"><strong>Verified physical quantity</strong>{props.isCountLocked && <span><Lock size={12} /> Approved physical count</span>}</div>
            <div className="allocation-focus-count-input"><input aria-label="Verified physical quantity" type="number" min="0" step="1" value={props.physicalCountInput} disabled={props.isCountLocked} placeholder="Enter count" onChange={event => props.onPhysicalCountChange(event.target.value)} /><span>units</span></div>
            {props.isCountLocked && <button type="button" className="allocation-focus-link" onClick={props.onUnlockCount}>Recount / edit total</button>}
          </div>
          {props.isVerifiedCountValid && props.verifiedCountNum < props.totalReservedAcrossStores && <p className="allocation-focus-error">The count cannot be lower than {props.totalReservedAcrossStores} units reserved for orders.</p>}
          <details className="allocation-focus-reference"><summary>View marketplace reference quantities</summary><p>Provider readings are not a physical count and never fill the JW ledger automatically.</p>
            {props.activeStores.filter(store => store.platform !== 'webstore').map(store => {
              const config = storeConfig(store);
              const facilities = config?.warehouses ?? [];
              const observed = props.providerReferences[store.id] ?? {};
              const count = facilities.filter(warehouse => observed[warehouse.id] !== undefined).length;
              const quantity = facilities.reduce((sum, warehouse) => sum + (observed[warehouse.id] ?? 0), 0);
              return <div className="allocation-focus-reference-row" key={store.id}><span>{store.nickname || store.name}</span><span>{props.providerReferenceLoading ? 'Checking…' : facilities.length && count === facilities.length ? `${quantity} units` : 'Read unavailable'}</span></div>;
            })}
            {Object.keys(props.providerReferenceFailures).length > 0 && <p>Some platform readings are unavailable. Check the affected warehouse when allocating.</p>}
          </details>
        </>}

        {props.step === 'input' && inputView === 'destinations' && <>
          <p className="allocation-focus-kicker">Step 2 · {selectedStore ? selectedStore.nickname || selectedStore.name : 'Destinations'}</p>
          <h3>{selectedStore ? 'Allocate to warehouses' : 'Where should the stock go?'}</h3>
          <p className="allocation-focus-intro">{selectedStore ? `Only these ${selectedConfig?.warehouses.length ?? 0} locations belong to this store. Enter each warehouse's verified allocation.` : 'Only mapped stores appear. Enter a quantity directly for stores without platform warehouses; open a store to allocate across its warehouses.'}</p>
          <div className={`allocation-focus-balance ${balanceClass}`} role="status"><div><strong>{balanceMessage}</strong><small>{props.isVerifiedCountValid ? `${props.verifiedCountNum} verified − ${allocated} allocated` : 'Waiting for a physical count'}</small></div><b>{props.isVerifiedCountValid ? remaining === 0 ? <Check size={23} /> : Math.abs(remaining) : '—'}</b></div>
          {selectedStore && selectedConfig ? <div className="allocation-focus-warehouse-list">
            {selectedConfig.warehouses.map(warehouse => {
              const balance = props.warehouseStockData[props.item.masterSku]?.[warehouse.id];
              const blocked = disabledStore(selectedStore, selectedConfig) || warehouse.status !== 'active' || !warehouse.inventoryWarehouseId || !!balance?.isCounted;
              const conflict = props.warehouseReservationConflicts[warehouse.id];
              return <div className="allocation-focus-warehouse" key={warehouse.id}><div><strong>{warehouse.warehouseName}</strong><small>{warehouse.codeValue}</small>{blocked && <small className="allocation-focus-warning">{balance?.isCounted ? 'Already counted' : 'Warehouse unavailable'}</small>}{conflict && <small className="allocation-focus-warning">Below {conflict.reserved} reserved units</small>}</div><input aria-label={`${selectedStore.nickname || selectedStore.name} ${warehouse.warehouseName} first count`} type="number" min="0" step="1" disabled={blocked} value={props.warehouseAllocations[warehouse.id] ?? ''} placeholder="0" onChange={event => props.onWarehouseAllocationChange(selectedStore.id, warehouse.id, event.target.value)} /></div>;
            })}
          </div> : <><p className="allocation-focus-section-label">Stock locations</p><div className="allocation-focus-store-list">{props.activeStores.map(store => {
            const config = storeConfig(store);
            if (!config || (store.platform !== 'webstore' && !config.isSkuMapped)) return null;
            const warehouses = config.warehouses;
            const blocked = disabledStore(store, config);
            const conflict = props.storeReservationConflicts[store.id];
            return <div className="allocation-focus-store" key={store.id}><div className="allocation-focus-store-identity"><span className="allocation-focus-platform"><MarketplaceLogo platform={store.platform} size="xs" /></span><div><strong>{store.nickname || store.name}</strong><small>{config.warehouseSyncStatus !== 'synced' ? 'Warehouse verification pending' : warehouses.length ? `${warehouses.length} platform ${warehouses.length === 1 ? 'warehouse' : 'warehouses'}` : store.platform === 'webstore' ? 'Your own stock' : 'No platform warehouse'}</small>{conflict && <small className="allocation-focus-warning">Below {conflict.reserved} reserved units</small>}</div></div><div className="allocation-focus-store-action">{warehouses.length ? <><b>{storeTotal(store)}</b><button type="button" disabled={blocked} onClick={() => setSelectedStoreId(store.id)}>Choose warehouses <ChevronDown size={13} className="allocation-focus-side-chevron" /></button></> : <input aria-label={`${store.nickname || store.name} allocation`} type="number" min="0" step="1" disabled={blocked || !!props.warehouseStockData[props.item.masterSku]?.[`direct:${store.id}`]?.isCounted} value={props.allocations[store.id] ?? ''} placeholder="0" onChange={event => props.onAllocationChange(store.id, event.target.value)} />}</div></div>;
          })}</div></>}
          {!props.canProceedToReview && blocker && <div className="allocation-focus-warning allocation-focus-blocker"><p>{blocker}</p><button type="button" onClick={props.onOpenStockTakeReview}>Open Stock Take Review</button></div>}
        </>}

        {props.step === 'review' && <>
          <p className="allocation-focus-kicker">Step 3 · Review</p><h3>Review stock allocation</h3><p className="allocation-focus-intro">Confirm the verified total and each destination before recording the allocation.</p>
          <div className="allocation-focus-balance is-balanced" role="status"><div><strong>All units allocated</strong><small>{props.verifiedCountNum} verified − {allocated} allocated</small></div><Check size={23} /></div>
          <div className="allocation-focus-review-list">{props.activeStores.map(store => {
            const config = storeConfig(store);
            if (!config || (store.platform !== 'webstore' && !config.isSkuMapped)) return null;
            return <section className="allocation-focus-review-store" key={store.id}><div className="allocation-focus-review-row"><div className="allocation-focus-store-identity"><span className="allocation-focus-platform"><MarketplaceLogo platform={store.platform} size="xs" /></span><div><strong>{store.nickname || store.name}</strong><small>{config.warehouses.length ? `${config.warehouses.length} ${config.warehouses.length === 1 ? 'warehouse' : 'warehouses'}` : 'Store ledger'}</small></div></div><b>{storeTotal(store)} units</b></div>
              {config.warehouses.length > 0 && <details className="allocation-focus-breakdown"><summary><span>Warehouse breakdown</span><ChevronDown size={15} /></summary><div className="allocation-focus-breakdown-list">{config.warehouses.map(warehouse => <div key={warehouse.id}><span>{warehouse.warehouseName}</span><b>{props.parsedWarehouseAllocations[warehouse.id] ?? 0} units</b></div>)}</div></details>}
            </section>;
          })}</div>
          <div className="allocation-focus-review-total"><strong>Total allocated</strong><strong>{allocated} / {props.verifiedCountNum} units</strong></div>
          <details className="allocation-focus-reference"><summary>Audit details</summary><p>Operator: {props.operator} · Reason: {props.reason}</p><label htmlFor="allocation-focus-notes">Notes (optional)</label><textarea id="allocation-focus-notes" value={props.notes} onChange={event => props.onNotesChange(event.target.value)} /></details>
          {props.saveError && <div className="allocation-focus-error" role="alert">{props.saveError}<button type="button" onClick={props.onOpenStockTakeReview}>Open Stock Take Review</button></div>}
        </>}

        {props.step === 'success' && <><div className="allocation-focus-success"><CheckCircle2 size={28} /><h3>Stock allocation completed</h3><p>{props.confirmedCount ?? props.verifiedCountNum} verified units were recorded in the JW ledger.</p></div><section className="allocation-focus-sync-status" aria-label="Marketplace stock status"><div className="allocation-focus-sync-heading"><div><strong>Marketplace stock status</strong><small>Checked against each allocated stock location.</small></div>{syncTargets.length > 0 && <button type="button" disabled={isCheckingSync} onClick={() => void checkSync(syncTargets, () => true)}>{isCheckingSync ? 'Checking…' : 'Refresh status'}</button>}</div>{syncTargets.length === 0 ? <p>No marketplace stock was allocated.</p> : syncTargets.map(target => { const status = syncStates[target.key]; return <div className="allocation-focus-sync-result" key={target.key}><span><MarketplaceLogo platform={target.store.platform} size="xs" /><span><strong>{target.store.nickname || target.store.name}</strong>{target.warehouse && <small>{target.warehouse.warehouseName}</small>}</span></span><span className="allocation-focus-sync-actions"><b className={status === 'synced' ? 'is-synced' : status === 'failed' ? 'is-failed' : ''}>{isCheckingSync && !status ? 'Checking…' : status === 'synced' ? 'Synced' : status === 'failed' ? 'Sync failed' : status === 'unavailable' ? 'Unable to check' : 'Pending'}</b>{props.onOpenStockSyncModal && <button type="button" className="allocation-focus-sync-details" onClick={() => props.onOpenStockSyncModal?.(props.item, target.store, target.warehouse ? Number(target.warehouse.id) : undefined)}>Details</button>}</span></div>; })}</section></>}
      </div>
      <footer className="allocation-focus-footer"><div className="allocation-focus-footer-step"><span>{props.step === 'success' ? 'COMPLETE' : props.step === 'review' ? 'STEP 3 OF 3' : inputView === 'count' ? 'STEP 1 OF 3' : 'STEP 2 OF 3'}</span><strong>{props.step === 'success' ? 'JW ledger saved' : props.step === 'review' ? 'Final review' : inputView === 'count' ? 'Verify total' : `${remaining} units remaining`}</strong></div><div className="allocation-focus-footer-actions"><button type="button" className="allocation-focus-secondary" onClick={back}>{props.step === 'success' ? 'Return to inventory' : props.step === 'input' && inputView === 'count' ? 'Cancel' : 'Back'}</button><button type="button" className="allocation-focus-primary" onClick={next} disabled={props.step === 'review' ? !props.canProceedToReview || props.isSaving : props.step === 'input' && inputView === 'count' ? !props.isVerifiedCountValid || props.verifiedCountNum < props.totalReservedAcrossStores : props.step === 'input' && !selectedStore ? !props.canProceedToReview : false}>{props.step === 'success' ? props.nextSkuCount ? `Next SKU (${props.nextSkuCount})` : 'Done' : props.step === 'review' ? props.isSaving ? 'Saving…' : 'Confirm allocation' : inputView === 'count' ? 'Allocate stock' : selectedStore ? 'Done with warehouses' : 'Review allocation'}</button></div></footer>
    </div>
  </div>;
};
