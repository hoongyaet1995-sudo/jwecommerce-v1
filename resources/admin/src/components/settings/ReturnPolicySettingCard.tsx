import React, { useEffect, useState } from 'react';
import { Save, ShieldCheck } from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';
import { fetchCommerceSettings, saveReturnPolicyConfiguration, type ReturnPolicySettings } from '../../integration/commerceSettingsData';

const DEFAULT_POLICY: ReturnPolicySettings = {
  enabled: true,
  returnWindowDays: 30,
  policySummary: 'Eligible delivered items may be returned or replaced within 30 days.',
};

export const ReturnPolicySettingCard: React.FC = () => {
  const { laravelBootstrap, addToast } = useAdmin();
  const [form, setForm] = useState(DEFAULT_POLICY);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let active = true;
    void fetchCommerceSettings(laravelBootstrap)
      .then(document => { if (active) setForm(document.returnPolicy ?? DEFAULT_POLICY); })
      .catch(error => addToast({ type: 'error', title: 'Return Policy Unavailable', message: error instanceof Error ? error.message : 'Unable to load return policy.' }))
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [addToast, laravelBootstrap]);

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    try {
      const document = await saveReturnPolicyConfiguration(laravelBootstrap, form);
      setForm(document.returnPolicy);
      addToast({ type: 'success', title: 'Return Policy Saved', message: form.enabled ? 'Customer return requests are enabled.' : 'New return requests are disabled; existing cases remain available.' });
    } catch (error) {
      addToast({ type: 'error', title: 'Return Policy Save Failed', message: error instanceof Error ? error.message : 'Unable to save return policy.' });
    } finally { setSaving(false); }
  };

  return <form onSubmit={save} className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 sm:p-8 shadow-sm space-y-6">
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-neutral-200 dark:border-neutral-800">
      <div><h2 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2"><ShieldCheck className="w-5 h-5 text-indigo-600 dark:text-indigo-400"/>Returns & Exchanges</h2><p className="text-xs text-neutral-500 mt-0.5">A minimal policy switch for customer requests and the existing RMA workbench.</p></div>
      <label className="inline-flex items-center gap-3 text-xs font-semibold cursor-pointer"><span>{form.enabled ? 'Enabled' : 'Disabled'}</span><input aria-label="Enable returns and exchanges" type="checkbox" checked={form.enabled} onChange={e=>setForm({...form,enabled:e.target.checked})} className="w-4 h-4 accent-indigo-600"/></label>
    </div>
    <div className="max-w-3xl space-y-4">
      <div className="grid sm:grid-cols-[180px_1fr] gap-4">
        <label className="space-y-1.5"><span className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Return window (days)</span><input type="number" min="1" max="365" required value={form.returnWindowDays} onChange={e=>setForm({...form,returnWindowDays:Number(e.target.value)})} className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs"/></label>
        <label className="space-y-1.5"><span className="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Customer-facing policy summary</span><textarea rows={3} maxLength={4000} required value={form.policySummary} onChange={e=>setForm({...form,policySummary:e.target.value})} className="w-full px-3.5 py-2 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs resize-y"/></label>
      </div>
      {!form.enabled&&<div className="p-3 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/30 text-xs text-amber-800 dark:text-amber-300">New customer requests and storefront entry points will be disabled. Existing RMA records remain visible and actionable.</div>}
      <div className="flex items-center justify-between gap-3 pt-2"><p className="text-[11px] text-neutral-500">Refunds are completed externally. JW Ecommerce records staff confirmation only.</p><button disabled={loading||saving} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold disabled:opacity-50"><Save className="w-4 h-4"/>{saving?'Saving…':'Save Return Policy'}</button></div>
    </div>
  </form>;
};
