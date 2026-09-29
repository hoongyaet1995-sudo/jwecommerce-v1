import React, { useEffect, useState } from 'react';
import { ArrowLeft, ClipboardCheck, RefreshCw, X } from 'lucide-react';
import { storeInventoryApi } from '../../../../../integration/storeInventory';
import { MarketplaceInventoryItem } from '../../../types';

type Source = 'marketplace' | 'manual';
type Observation = { quantity: number; observed_at: string; provider_location_id: string | null };

interface Props {
  item: MarketplaceInventoryItem;
  storeId: string;
  facilityId: string;
  storeName: string;
  facilityName: string;
  onClose: () => void;
  onSaved: () => Promise<void>;
}

/** Exact-location First Count; the destination was selected in Manage Stock. */
export const LocationFirstCountBaseline: React.FC<Props> = ({ item, storeId, facilityId,
  storeName, facilityName, onClose, onSaved }) => {
  const website = storeId === 'website';
  const numericFacilityId = /^\d+$/.test(facilityId) ? Number(facilityId) : null;
  const [source, setSource] = useState<Source>(website ? 'manual' : 'marketplace');
  const [observation, setObservation] = useState<Observation | null>(null);
  const [manualQuantity, setManualQuantity] = useState('');
  const [stage, setStage] = useState<'source' | 'review' | 'complete'>('source');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [receipt, setReceipt] = useState<any>(null);
  const [allowOverwrite, setAllowOverwrite] = useState(false);
  const [locationName, setLocationName] = useState(facilityName);
  const [operationKey] = useState(() => crypto.randomUUID());
  const variantId = Number((item as any).variantId);

  useEffect(() => { setObservation(null); setError(''); }, [source]);
  useEffect(() => {
    if (numericFacilityId === null) return;
    let active = true;
    void storeInventoryApi.facilityBalances(storeId, variantId).then((result: any) => {
      const facility = (result.facilities || []).find((row: any) => Number(row.id) === numericFacilityId);
      if (active && facility?.name) setLocationName(facility.name);
    }).catch(() => {});
    return () => { active = false; };
  }, [storeId, numericFacilityId, variantId]);

  const read = async () => {
    setBusy(true); setError('');
    try {
      const result = await storeInventoryApi.baselineObserve(storeId, numericFacilityId, variantId);
      setObservation(result);
    } catch (failure: any) { setError(failure?.message || 'Marketplace stock could not be read.'); }
    finally { setBusy(false); }
  };
  useEffect(() => { if (source === 'manual' && !website) void read(); }, [source, storeId, numericFacilityId, variantId]);
  const quantity = source === 'marketplace' ? observation?.quantity : Number(manualQuantity);
  const valid = quantity !== undefined && Number.isInteger(quantity) && quantity >= 0
    && (source === 'marketplace' || manualQuantity.trim() !== '');
  const commit = async () => {
    if (!valid || busy) return;
    setBusy(true); setError('');
    try {
      const result = await storeInventoryApi.baselineCommit({ store_key: storeId,
        facility_id: numericFacilityId, variant_id: variantId, source,
        quantity: quantity!, expected_provider_quantity: observation?.quantity ?? null,
        operation_key: operationKey, allow_provider_overwrite: allowOverwrite });
      setReceipt(result);
      await onSaved();
      setStage('complete');
    } catch (failure: any) {
      setError(failure?.message || 'First Count could not be saved.');
      if (source === 'marketplace') setObservation(null);
      setStage('source');
    } finally { setBusy(false); }
  };

  return <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-3 text-neutral-100 backdrop-blur-sm sm:p-6">
    <div role="dialog" aria-modal="true" aria-label="First Count this location" className="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-neutral-700 bg-neutral-900 shadow-2xl">
      <header className="flex items-start justify-between gap-4 border-b border-neutral-800 px-5 py-5 sm:px-6">
        <div><div className="mb-2 text-[10px] font-bold uppercase tracking-widest text-purple-300">Manage Stock · Independent mode</div>
          <h2 className="text-lg font-bold text-white">First Count this location</h2>
          <p className="mt-1 text-xs text-neutral-400">{storeName} · {locationName}</p></div>
        <button type="button" aria-label="Close" onClick={onClose} className="rounded p-1 text-neutral-400 hover:text-white"><X className="h-5 w-5" /></button>
      </header>
      <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
        <div className="flex items-center gap-3 rounded-xl border border-neutral-800 bg-neutral-800/70 p-3">
          <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-neutral-700">{item.imageUrl ? <img src={item.imageUrl} alt="" className="h-full w-full object-cover" /> : <ClipboardCheck className="h-5 w-5" />}</div>
          <div className="min-w-0"><strong className="block truncate text-sm text-white">{item.masterSku}</strong><span className="text-xs text-neutral-400">{item.productName}</span></div>
        </div>
        {stage === 'source' && <>
          <p className="text-xs text-neutral-400">This First Count applies only to the selected SKU and stock location. Other stores and warehouses stay unchanged.</p>
          {!website && <button type="button" onClick={() => setSource('marketplace')} className={`w-full rounded-xl border p-4 text-left text-sm ${source === 'marketplace' ? 'border-purple-500 bg-purple-950/30' : 'border-neutral-700 bg-neutral-800'}`}><strong>Read current Marketplace stock</strong><span className="mt-1 block text-xs text-neutral-400">Use the live provider quantity as the candidate JW Current Stock baseline.</span></button>}
          <button type="button" onClick={() => setSource('manual')} className={`w-full rounded-xl border p-4 text-left text-sm ${source === 'manual' ? 'border-purple-500 bg-purple-950/30' : 'border-neutral-700 bg-neutral-800'}`}><strong>Enter a manual First Count</strong><span className="mt-1 block text-xs text-neutral-400">Count and enter the Current Stock at this location.</span></button>
          {source === 'marketplace' && <div className="rounded-xl border border-neutral-700 bg-neutral-950 p-4">
            {observation ? <><strong className="text-2xl text-emerald-400">{observation.quantity} units</strong><p className="mt-1 text-xs text-neutral-400">Read {new Date(observation.observed_at).toLocaleString()} · Provider location {observation.provider_location_id || 'Default Inventory'}</p></> : <p className="text-xs text-neutral-400">No provider quantity read yet.</p>}
            <button type="button" disabled={busy} onClick={() => void read()} className="mt-3 flex items-center gap-2 rounded-lg border border-purple-600 px-3 py-2 text-xs font-bold text-purple-200 disabled:opacity-40"><RefreshCw className="h-3 w-3" />{busy ? 'Reading…' : observation ? 'Read again' : 'Read Marketplace stock'}</button>
          </div>}
          {source === 'manual' && <><label className="block text-xs font-semibold text-neutral-300">Current Stock<input aria-label="Current Stock" type="number" min="0" step="1" value={manualQuantity} onChange={event => setManualQuantity(event.target.value)} className="mt-2 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-white" /></label>{!website && <p className="text-xs text-neutral-400">{busy ? 'Reading Marketplace for comparison…' : observation ? `Marketplace currently reports ${observation.quantity} units at this location.` : 'Marketplace comparison unavailable. JW count can be saved locally; no initial push will be queued.'}</p>}</>}
        </>}
        {stage === 'review' && <div className="space-y-4"><div className="rounded-xl border border-neutral-700 bg-neutral-950 p-4"><p className="text-xs text-neutral-400">Candidate Current Stock · {storeName} · {locationName}</p><strong className="mt-2 block text-2xl text-emerald-400">{quantity} units</strong><p className="mt-1 text-xs text-neutral-400">SKU {item.masterSku}{observation ? ` · Read ${new Date(observation.observed_at).toLocaleString()}` : ''}</p></div><p className="rounded-xl border border-amber-800 bg-amber-950/30 p-3 text-xs text-amber-200">Orders created before this location’s baseline are historical and will not deduct this quantity again. New orders are counted once. {source === 'marketplace' ? 'JW will re-read this exact location before saving; a changed result requires a new confirmation. No initial stock push will be sent.' : observation && observation.quantity !== quantity ? 'Manual count differs from the Marketplace. JW will send the confirmed quantity to this exact location.' : observation ? 'The manual count matches Marketplace; no initial stock push is needed.' : 'Marketplace comparison is unavailable. JW will save locally without an initial stock push.'}</p>{source === 'manual' && observation && observation.quantity !== quantity && <label className="flex items-start gap-2 text-xs text-amber-200"><input type="checkbox" checked={allowOverwrite} onChange={event => setAllowOverwrite(event.target.checked)} />I confirm JW Current Stock should overwrite the Marketplace quantity at this location.</label>}</div>}
        {stage === 'complete' && <div className="rounded-xl border border-emerald-800 bg-emerald-950/30 p-4 text-sm text-emerald-200"><strong>JW Current Stock saved: {receipt?.current_stock} units</strong><p className="mt-2 text-xs">{receipt?.provider_sync_status === 'matched_at_import' ? 'Marketplace readback matched this baseline. No initial stock push was sent.' : receipt?.provider_sync_status === 'local_only' ? 'JW saved locally. No initial Marketplace stock push was sent.' : 'Check the Marketplace sync status for this location.'}</p></div>}
        {error && <p role="alert" className="rounded-lg border border-rose-800 bg-rose-950/30 p-3 text-xs text-rose-300">{error}</p>}
      </div>
      <footer className="flex items-center justify-between gap-3 border-t border-neutral-800 bg-neutral-950/70 px-5 py-4 sm:px-6"><button type="button" onClick={stage === 'review' ? () => setStage('source') : onClose} className="flex items-center gap-1 rounded-lg border border-neutral-700 px-4 py-2 text-xs text-neutral-300"><ArrowLeft className="h-3 w-3" />{stage === 'review' ? 'Back' : 'Close'}</button>{stage === 'source' ? <button type="button" disabled={!valid || busy} onClick={() => setStage('review')} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-40">Review First Count</button> : stage === 'review' ? <button type="button" disabled={busy || (source === 'manual' && !!observation && observation.quantity !== quantity && !allowOverwrite)} onClick={() => void commit()} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-40">{busy ? 'Checking & saving…' : 'Confirm First Count'}</button> : <button type="button" onClick={onClose} className="rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white">Done</button>}</footer>
    </div>
  </div>;
};
