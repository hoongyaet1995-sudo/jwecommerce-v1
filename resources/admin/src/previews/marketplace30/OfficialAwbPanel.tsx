import React from 'react';
import {X, Printer, Download} from 'lucide-react';
import {MarketplaceLogo} from './components/marketplace/MarketplaceLogo';
import type {MarketplaceOrderDetail} from '../../integration/marketplaceOperations';

// Source-derived AWB modal shell. The label body is the provider PDF, never a synthetic template.
export function OfficialAwbPanel({order, onClose}: {order: MarketplaceOrderDetail; onClose: () => void}) {
  const packages = order.label_packages ?? [];
  const [packageId, setPackageId] = React.useState(packages[0] ?? '');
  const [pdfUrl, setPdfUrl] = React.useState<string | null>(null);
  const [message, setMessage] = React.useState('Loading official label…');
  const frame = React.useRef<HTMLIFrameElement>(null);

  React.useEffect(() => {
    if (!packageId) return;
    let active = true;
    let url: string | null = null;
    setPdfUrl(null);
    setMessage('Loading official label…');
    fetch(`/admin/api/marketplace/orders/${order.id}/label?packageId=${encodeURIComponent(packageId)}`,
      {credentials: 'same-origin', headers: {Accept: 'application/pdf'}})
      .then(async response => {
        if (!response.ok || !response.headers.get('content-type')?.includes('application/pdf')) throw new Error('Label unavailable');
        return response.blob();
      })
      .then(blob => {
        if (!active) return;
        url = URL.createObjectURL(blob);
        setPdfUrl(url);
        setMessage('Official platform PDF');
      })
      .catch(() => { if (active) setMessage('Label download failed. Renew access or refresh the order.'); });
    return () => { active = false; if (url) URL.revokeObjectURL(url); };
  }, [order.id, packageId]);

  return <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto" role="dialog" aria-modal="true" aria-label="Official marketplace airway bill">
    <div className="relative w-full max-w-2xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 overflow-hidden my-8">
      <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/50">
        <div className="flex items-center gap-2.5">
          <div className="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800"><Printer className="w-5 h-5" /></div>
          <div><div className="flex items-center gap-2"><h3 className="text-sm font-bold text-neutral-900 dark:text-white">Official Marketplace Airway Bill (AWB)</h3><MarketplaceLogo platform={order.platform} size={16}/></div>
            <p className="text-xs text-neutral-500">Order #{order.external_order_id} • {order.platform.toUpperCase()} Open API</p></div>
        </div>
        <div className="flex items-center gap-2">
          <button disabled={!pdfUrl} onClick={() => frame.current?.contentWindow?.print()} className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer disabled:opacity-50"><Printer className="w-3.5 h-3.5"/>Print Label</button>
          <button onClick={onClose} aria-label="Close airway bill" className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors cursor-pointer"><X className="w-5 h-5"/></button>
        </div>
      </div>
      {packages.length > 1 && <div className="flex gap-2 px-6 py-2 overflow-x-auto">{packages.map(id => <button key={id} onClick={() => setPackageId(id)} className={`px-3 py-1 text-xs rounded-lg border ${id === packageId ? 'border-indigo-500 text-indigo-600' : 'border-neutral-300 text-neutral-500'}`}>Package {id}</button>)}</div>}
      <div className="p-6 bg-neutral-100 dark:bg-neutral-950 flex justify-center min-h-[420px]">
        {pdfUrl ? <iframe ref={frame} src={pdfUrl} title={`Official AWB package ${packageId}`} className="w-full h-[600px] bg-white border border-neutral-300"/> : <p className="text-xs text-neutral-500 self-center">{message}</p>}
      </div>
      <div className="flex items-center justify-between px-6 py-4 border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900">
        <span className="text-xs text-neutral-500">{message} · Tracking: {order.tracking_number ?? 'Not provided'}</span>
        <div className="flex items-center gap-3">{pdfUrl && <a href={pdfUrl} download={`lazada-${packageId}.pdf`} className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600"><Download className="w-3.5 h-3.5"/>Download PDF</a>}
          <button onClick={onClose} className="px-4 py-2 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-xl transition-colors cursor-pointer">Close</button></div>
      </div>
    </div>
  </div>;
}
