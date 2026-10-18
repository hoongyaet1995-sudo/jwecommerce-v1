import React, { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ArrowRight, Check, Eye, EyeOff, MessageSquareText, LayoutTemplate, ShieldCheck, Sparkles } from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';
import { aiRequest, AIProvider, providerLabels, useAIConnections, verifyAISettings } from '../../integration/aiConnections';
import { modelOptions } from '../../integration/aiModelRecommendations';

const panel = 'rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-xs';
const muted = 'text-sm text-neutral-500 dark:text-neutral-400';
const input = 'w-full rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-950 px-3.5 py-3 text-sm text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500';
const primary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500';

export function IntegrationHub() {
  const { setCurrentSection } = useAdmin();
  const { connections, loading, error } = useAIConnections();
  const active = connections.find(connection => connection.enabled);
  return <div className="space-y-7">
    <div><h2 className="text-2xl font-bold tracking-tight">Integration</h2><p className={`${muted} mt-2`}>Manage services connected to JW Ecom.</p></div>
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
      <button type="button" onClick={() => setCurrentSection('integration-ai-connect')} className={`${panel} p-6 text-left group hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors focus-visible:outline-2 focus-visible:outline-indigo-500`}>
        <div className="flex items-center justify-between gap-3"><span className="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400"><Sparkles className="w-6 h-6" /></span><span className="text-xs px-3 py-1.5 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-500 dark:text-neutral-400">{loading ? 'Loading…' : error ? 'Unavailable' : active ? 'Configured' : 'Not connected'}</span></div>
        <h3 className="text-lg font-bold mt-7">AI Connect</h3><p className={`${muted} mt-2 leading-relaxed`}>Configure your AI provider for Support Chat and CMS page building.</p>
        <div className="mt-6 flex items-center gap-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400">Configure <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" /></div>
      </button>
    </div>
    <p className="text-xs text-neutral-500 dark:text-neutral-400">{error || (active ? `${active.label} configured. Support and CMS consumer integration is the next step.` : 'Connect and verify your provider to prepare AI features.')}</p>
  </div>;
}

export function AIConnectPage() {
  const { setCurrentSection } = useAdmin();
  const { connections, endpoints, loading, error: loadError, reload } = useAIConnections();
  const [enabled, setEnabled] = useState(false);
  const [provider, setProvider] = useState<AIProvider>('openai');
  const [baseUrl, setBaseUrl] = useState('');
  const [key, setKey] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [textModel, setTextModel] = useState('');
  const [imageModel, setImageModel] = useState('');
  const [support, setSupport] = useState(true);
  const [builder, setBuilder] = useState(true);
  const [search, setSearch] = useState(false);
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const pending = useRef(false);
  const verifiedEnableIntent = useRef<boolean | null>(null);
  const connection = connections.find(item => item.provider === provider);
  const textOptions = modelOptions(provider, 'text', connection?.models || [], textModel);
  const imageOptions = modelOptions(provider, 'image', connection?.models || [], imageModel);
  useEffect(() => {
    setKey(''); setShowKey(false); setEnabled(verifiedEnableIntent.current ?? (connection?.enabled || false));
    verifiedEnableIntent.current = null;
    setBaseUrl(connection?.base_url || ''); setTextModel(connection?.text_model || ''); setImageModel(connection?.image_model || '');
    setSupport(connection?.support_enabled || false); setBuilder(connection?.builder_enabled || false); setSearch(connection?.search_enabled || false);
  }, [connection, provider]);
  const dirty = !!key || enabled !== (connection?.enabled || false) || baseUrl !== (connection?.base_url || '')
    || textModel !== (connection?.text_model || '') || imageModel !== (connection?.image_model || '')
    || support !== (connection?.support_enabled || false) || builder !== (connection?.builder_enabled || false) || search !== (connection?.search_enabled || false);
  async function run(action: 'save' | 'verify') {
    if (pending.current || !connection) return;
    pending.current = true; setBusy(true); setNotice('');
    let wroteDraft = false;
    const draft = {
      enabled, support_enabled: support, builder_enabled: builder, search_enabled: search,
      text_model: key ? null : textModel || null, image_model: key ? null : imageModel || null,
      base_url: baseUrl || null, ...(key ? { api_key: key } : {}),
    };
    try {
      if (action === 'verify') {
        await verifyAISettings(provider, connection.version, dirty ? draft : undefined, () => {
          wroteDraft = true; setKey(''); setShowKey(false);
        });
      } else {
        await aiRequest('/' + provider, 'PUT', { ...draft, version: connection.version });
      }
      if (action === 'verify') verifiedEnableIntent.current = enabled;
      setKey(''); setShowKey(false); await reload();
      setNotice(action === 'save' ? 'Settings saved.' : 'Key verified and models refreshed. Choose your models, then enable and save.');
    } catch (failure) { setNotice((failure as Error).message); if (action !== 'save' && (!dirty || wroteDraft)) await reload(); }
    finally { pending.current = false; setBusy(false); }
  }
  const save = (event: React.FormEvent) => { event.preventDefault(); void run('save'); };
  const disabled = busy || loading || !!loadError;
  return <form onSubmit={save} className="space-y-6" autoComplete="off" aria-busy={busy || loading}>
    <div><h2 className="text-2xl font-bold tracking-tight">AI Connect</h2><p className={`${muted} mt-2`}>One AI connection for Support Chat, CMS page building and search optimization.</p></div>
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div><p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">AI Integration</p><p className="text-sm font-medium mt-1">Configure your provider and models.</p></div>
      <div className="flex flex-wrap gap-2"><button type="button" disabled={busy} onClick={() => setCurrentSection('integration')} className="inline-flex items-center gap-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-4 py-3 text-sm font-semibold hover:bg-neutral-50 dark:hover:bg-neutral-800"><ArrowLeft className="w-4 h-4" />Back to Integrations</button><button type="submit" disabled={disabled} className={`${primary} disabled:opacity-50`}><Check className="w-4 h-4" />{busy ? 'Working…' : 'Save Changes'}</button></div>
    </div>
    <div className="rounded-xl border border-indigo-100 dark:border-indigo-900 bg-indigo-50 dark:bg-indigo-950/30 px-4 py-3 text-sm text-indigo-700 dark:text-indigo-300">Save &amp; Verify Models refreshes the model lists. Choose your models → enable and save. Each feature has an independent permission below.</div>
    {loadError && <div role="alert" className={muted}>{loadError} <button type="button" onClick={() => void reload()} className="underline">Reload</button></div>}
    {notice && <div role="status" className="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-4 py-3 text-sm">{notice}</div>}
    <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1.65fr)_minmax(280px,1fr)] gap-6 items-start">
      <fieldset disabled={disabled} className="space-y-6 min-w-0">
        <section className={`${panel} p-5 sm:p-6 space-y-5`} aria-labelledby="provider-title">
          <div><h3 id="provider-title" className="text-lg font-bold">AI Provider</h3><p className={`${muted} mt-1`}>Choose the provider you want to connect.</p></div>
          <div className="flex items-center justify-between gap-4 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50"><div><p className="text-sm font-semibold">Enable AI Connect</p><p className="text-xs text-neutral-500 dark:text-neutral-400 mt-1">Choose On to enable after setup. Verify your key, select a text model, then save to activate.</p></div><button type="button" role="switch" aria-label="Enable AI Connect" aria-checked={enabled} onClick={() => setEnabled(!enabled)} className={`shrink-0 w-10 h-6 rounded-full p-1 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 ${enabled ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700'}`}><span className={`block w-4 h-4 bg-white rounded-full transition-transform ${enabled ? 'translate-x-4' : ''}`} /></button></div>
          <div><label htmlFor="ai-provider" className="block text-sm font-semibold mb-2">Provider</label><select id="ai-provider" value={provider} onChange={e => { setProvider(e.target.value as AIProvider); setNotice(''); }} className={input}>{Object.entries(providerLabels).map(([id,label]) => <option key={id} value={id}>{label}</option>)}</select><p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">Each provider keeps its own key and model settings.</p></div>
          {provider === 'compatible' && <div><label htmlFor="ai-base-url" className="block text-sm font-semibold mb-2">API Base URL</label><select id="ai-base-url" value={baseUrl} onChange={e => { setBaseUrl(e.target.value); setTextModel(''); setImageModel(''); setEnabled(false); }} className={input}><option value="">Select an approved endpoint</option>{endpoints.map(url => <option key={url}>{url}</option>)}</select><p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">{endpoints.length ? 'Only server-approved HTTPS endpoints are available.' : 'Add the provider endpoint to the server allowlist before connecting.'}</p></div>}
          <div><label htmlFor="ai-key" className="block text-sm font-semibold mb-2">{providerLabels[provider]} API Key</label><div className="relative"><input id="ai-key" type={showKey ? 'text' : 'password'} value={key} onChange={e => { setKey(e.target.value); if(e.target.value) { setTextModel(''); setImageModel(''); } }} placeholder={connection?.has_key ? 'Key saved · leave blank to keep it' : 'Paste your API key'} spellCheck={false} className={`${input} pr-12`} /><button type="button" aria-label={showKey ? 'Hide API key' : 'Show API key'} onClick={() => setShowKey(!showKey)} className="absolute right-3 top-3 p-0.5 text-neutral-500 hover:text-indigo-500">{showKey ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}</button></div><p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">Encrypted on the server. Saved keys are never returned to the browser.</p></div>
          <div className="flex flex-wrap gap-2"><button type="button" disabled={!key && !connection?.has_key} onClick={() => void run('verify')} className={`${primary} disabled:opacity-50`}>{dirty ? 'Save & Verify Models' : 'Verify & Load Models'}</button></div><p className="text-xs text-neutral-500 dark:text-neutral-400">Verification refreshes the model lists without sending a generation request.</p>
        </section>
        <section className={`${panel} p-5 sm:p-6 space-y-5`} aria-labelledby="model-title"><div><h3 id="model-title" className="text-lg font-bold">Model Selection</h3><p className={`${muted} mt-1`}>Three OpenAI recommendations for each task. Only models returned for your key can be selected.</p></div><div className="grid sm:grid-cols-2 gap-4"><div><label htmlFor="ai-text-model" className="block text-sm font-semibold mb-2">Text Model</label><select id="ai-text-model" value={textModel} onChange={e => setTextModel(e.target.value)} className={input}><option value="">Select a verified model</option>{textOptions.recommended.length > 0 && <optgroup label="Recommended models">{textOptions.recommended.map(m => <option key={m.id} value={m.id} disabled={!m.available}>{m.label}{!m.available ? ' · Not available for this key' : ''}</option>)}</optgroup>}{textOptions.others.length > 0 && <optgroup label="Other available models">{textOptions.others.map(m => <option key={m.id}>{m.id}</option>)}</optgroup>}</select><p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">Support answers and page content.</p></div><div><label htmlFor="ai-image-model" className="block text-sm font-semibold mb-2">Image Model <span className="font-normal text-neutral-500">(optional)</span></label><select id="ai-image-model" value={imageModel} onChange={e => setImageModel(e.target.value)} className={input}><option value="">No image model selected</option>{imageOptions.recommended.length > 0 && <optgroup label="Recommended image models">{imageOptions.recommended.map(m => <option key={m.id} value={m.id} disabled={!m.available}>{m.label}{!m.available ? ' · Not available for this key' : ''}</option>)}</optgroup>}{imageOptions.others.length > 0 && <optgroup label="Other available image models">{imageOptions.others.map(m => <option key={m.id}>{m.id}</option>)}</optgroup>}</select><p className="text-xs text-neutral-500 dark:text-neutral-400 mt-2">Image model selection is prepared; image generation is not connected yet.</p></div></div></section>
        <section className={`${panel} p-5 sm:p-6 space-y-4`}><div><h3 className="text-lg font-bold">Use AI For</h3><p className={`${muted} mt-1`}>Select the features that will use this connection.</p></div>{[{name:'Support Chat', description:'FAQ answers for storefront customers.', icon:MessageSquareText, value:support, setter:setSupport}, {name:'Search optimization', description:'Optimize search metadata from saved business facts.', icon:LayoutTemplate, value:search, setter:setSearch}, {name:'CMS Vibe Page Builder', description:'Assist with page content and imagery.', icon:LayoutTemplate, value:builder, setter:setBuilder}].map(item => <label key={item.name} className="flex items-center gap-3 p-4 rounded-xl border border-neutral-200 dark:border-neutral-800 cursor-pointer"><item.icon className="w-5 h-5 text-indigo-500 shrink-0" /><span className="flex-1"><span className="block text-sm font-semibold">{item.name}</span><span className="block text-xs text-neutral-500 dark:text-neutral-400 mt-1">{item.description}</span></span><input type="checkbox" checked={item.value} onChange={e => item.setter(e.target.checked)} className="w-4 h-4 accent-indigo-600" /></label>)}</section>
      </fieldset>
      <aside className={`${panel} p-5 sm:p-6 xl:sticky xl:top-0`} aria-label="AI connection summary"><span className="inline-flex p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400"><Sparkles className="w-6 h-6" /></span><h3 className="text-lg font-bold mt-4">AI Connection</h3><p className={`${muted} mt-1 leading-relaxed`}>A shared provider for your selected AI features.</p><dl className="mt-4 divide-y divide-neutral-200 dark:divide-neutral-800">{[['Configuration', loading ? 'Loading…' : connection?.verified_at ? 'Key verified' : 'Not verified'],['AI Consumers','Not connected yet'],['Connection',connection?.enabled ? 'Enabled' : 'Disabled'],['Provider',providerLabels[provider]],['Text Model',connection?.text_model || 'Not selected'],['Image Model',connection?.image_model || 'Not selected'],['API Key',connection?.has_key ? 'Saved securely' : 'Not saved'],['Changes',dirty ? 'Unsaved' : 'Saved']].map(([name,value]) => <div key={name} className="flex justify-between gap-4 py-3.5 text-sm"><dt className="text-neutral-500 dark:text-neutral-400 shrink-0">{name}</dt><dd className="font-semibold text-right break-all">{value}</dd></div>)}</dl><button type="submit" disabled={disabled} className={`${primary} w-full mt-3 disabled:opacity-50`}><Check className="w-4 h-4" />Save AI Settings</button><div className="flex gap-2 mt-4 text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed"><ShieldCheck className="w-4 h-4 shrink-0" /><p>Credentials stay on the server. Verification uses your saved settings to refresh the model lists.</p></div></aside>
    </div>
  </form>;
}

