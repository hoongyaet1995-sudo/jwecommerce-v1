import React, { useState } from 'react';
import { MarketplaceInventoryItem } from '../../../types';

interface Props {
  item: MarketplaceInventoryItem;
  onClose: () => void;
  onSave: (count: number, reason: string) => Promise<void>;
}

export const SharedFirstCountModal: React.FC<Props> = ({ item, onClose, onSave }) => {
  const [count, setCount] = useState('');
  const [reason, setReason] = useState('First Count after stock mode reset');
  const [step, setStep] = useState<'input' | 'review' | 'saved'>('input');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const valid = /^\d+$/.test(count.trim()) && Number.isSafeInteger(Number(count));
  const save = async () => {
    if (!valid || saving) return;
    setSaving(true);
    setError('');
    try { await onSave(Number(count), reason.trim()); setStep('saved'); }
    catch (cause: any) { setError(cause?.message || 'First Count could not be saved.'); }
    finally { setSaving(false); }
  };
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/75 p-4 text-neutral-900 dark:text-white" role="dialog" aria-modal="true" aria-label="Shared Pool First Count">
    <div className="w-full max-w-lg rounded-2xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900">
      <header className="border-b border-neutral-200 px-6 py-5 dark:border-neutral-800"><h2 className="text-lg font-bold">First Count · Shared Stock Pool</h2><p className="mt-1 text-sm text-neutral-500">{item.masterSku} · {item.productName}</p></header>
      <div className="space-y-4 p-6">
        {step === 'saved' ? <p className="rounded-lg border border-emerald-700 bg-emerald-950/30 p-4 text-sm text-emerald-300">JW First Count saved. Future orders can now update this SKU’s shared ledger.</p> : <>
          <p className="text-sm text-neutral-500">Enter the verified physical stock for this SKU. This opens one shared JW ledger; it does not distribute stock across stores.</p>
          <label className="block text-sm font-semibold">Current Stock<input type="number" min="0" step="1" value={count} onChange={event => setCount(event.target.value)} disabled={step === 'review'} className="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent px-3 py-2 dark:border-neutral-700" /></label>
          <label className="block text-sm font-semibold">Reason<input value={reason} onChange={event => setReason(event.target.value)} disabled={step === 'review'} className="mt-2 w-full rounded-lg border border-neutral-300 bg-transparent px-3 py-2 dark:border-neutral-700" /></label>
          {step === 'review' && <p className="rounded-lg border border-amber-700 bg-amber-950/30 p-4 text-sm text-amber-200">Confirm First Count of {count} for {item.masterSku}. Orders created before this count will not be deducted later.</p>}
        </>}
        {error && <p role="alert" className="text-sm text-red-400">{error}</p>}
      </div>
      <footer className="flex justify-end gap-2 border-t border-neutral-200 px-6 py-4 dark:border-neutral-800"><button type="button" onClick={step === 'review' ? () => setStep('input') : onClose} className="rounded-lg border border-neutral-600 px-4 py-2 text-sm">{step === 'review' ? 'Back' : 'Close'}</button>{step !== 'saved' && <button type="button" disabled={!valid || !reason.trim() || saving} onClick={step === 'review' ? save : () => setStep('review')} className="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{saving ? 'Saving…' : step === 'review' ? 'Confirm First Count' : 'Review First Count'}</button>}</footer>
    </div>
  </div>;
};
