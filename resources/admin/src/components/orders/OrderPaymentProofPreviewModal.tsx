import React from 'react';
import { AlertCircle, Download, FileText, ShieldCheck, X } from 'lucide-react';
import type { PaymentProof } from '../../types';

interface Props {
  isOpen: boolean;
  orderNumber: string;
  paymentMethod: string;
  paymentProvider?: string | null;
  paymentReference?: string | null;
  proof?: PaymentProof;
  onDownload: () => void;
  onClose: () => void;
}

export const OrderPaymentProofPreviewModal: React.FC<Props> = ({
  isOpen, orderNumber, paymentMethod, paymentProvider, paymentReference, proof, onDownload, onClose,
}) => {
  if (!isOpen) return null;

  const isImage = Boolean(proof?.mimeType?.startsWith('image/'));
  const isPdf = proof?.mimeType === 'application/pdf';
  const reviewer = proof?.audit?.find(event => event.eventType === 'verified' || event.eventType === 'rejected');

  return (
    <div className="fixed inset-0 z-60 flex items-center justify-center p-3 sm:p-6 bg-black/75 backdrop-blur-xs animate-in fade-in">
      <div className="w-full max-w-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-3xl p-6 shadow-2xl max-h-[92vh] overflow-y-auto space-y-5">
        <div className="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-mono font-bold text-base text-neutral-900 dark:text-white">Payment Proof Receipt</span>
              <span className="text-xs font-mono text-neutral-400">#{orderNumber}</span>
              {proof?.status === 'verified' && <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800"><ShieldCheck className="w-2.5 h-2.5" /> Verified</span>}
              {proof?.status === 'rejected' && <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800"><AlertCircle className="w-2.5 h-2.5" /> Rejected</span>}
              {proof?.status === 'uploaded' && <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800"><FileText className="w-2.5 h-2.5" /> Pending Review</span>}
            </div>
            <p className="text-[11px] text-neutral-400 mt-0.5">Authorized internal viewer. File access is logged.</p>
          </div>
          <button type="button" onClick={onClose} className="p-1.5 rounded-xl text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"><X className="w-5 h-5" /></button>
        </div>

        <div className="rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/40 p-5 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-mono">
            <div className="p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-1">
              <span className="text-[10px] text-neutral-400 block uppercase font-bold">Payment Method & Provider</span>
              <div className="font-bold text-neutral-900 dark:text-white text-xs">{paymentMethod}</div>
              <div className="text-neutral-500 text-[11px]">{paymentProvider || '—'}</div>
              {paymentReference && <div className="text-neutral-600 dark:text-neutral-400 text-[11px]">Ref: {paymentReference}</div>}
            </div>
            <div className="p-3 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 space-y-1">
              <span className="text-[10px] text-neutral-400 block uppercase font-bold">Proof Attachment Details</span>
              <div className="font-bold text-neutral-900 dark:text-white text-xs truncate">{proof?.originalFileName || '—'}</div>
              <div className="text-neutral-500 text-[11px]">MIME: {proof?.mimeType || '—'}</div>
              {proof?.uploadedAt && <div className="text-neutral-600 dark:text-neutral-400 text-[11px]">Uploaded: {new Date(proof.uploadedAt).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}</div>}
            </div>
          </div>

          <div className="p-4 rounded-xl bg-neutral-900 text-white space-y-2 border border-neutral-800 text-xs">
            <div className="flex items-center justify-between text-neutral-400 text-[11px]">
              <div className="flex items-center gap-1.5"><FileText className="w-3.5 h-3.5 text-indigo-400" /><span className="font-mono text-neutral-300">{proof?.originalFileName || 'Document Preview'}</span></div>
              <span className="text-[10px] text-neutral-400 font-mono">{proof?.mimeType || 'Protected Stream'}</span>
            </div>
            {proof?.previewUrl && isImage && <div className="rounded-lg overflow-hidden bg-neutral-950 border border-neutral-800/80 flex items-center justify-center p-2 min-h-[180px] max-h-[380px]"><img src={proof.previewUrl} alt={proof.originalFileName || 'Payment proof preview'} className="max-h-[360px] w-auto max-w-full object-contain rounded" referrerPolicy="no-referrer" /></div>}
            {proof?.previewUrl && isPdf && <div className="rounded-lg overflow-hidden bg-neutral-950 border border-neutral-800/80 h-80"><iframe src={proof.previewUrl} title={proof.originalFileName || 'Payment proof PDF'} className="w-full h-full border-0" /></div>}
            {(!proof?.previewUrl || (!isImage && !isPdf)) && <div className="h-36 rounded-lg bg-neutral-950 border border-neutral-800/80 flex flex-col items-center justify-center p-4 text-center"><FileText className="w-7 h-7 text-indigo-400 mb-1" /><p className="text-xs font-semibold text-neutral-200">{proof?.originalFileName || 'Preview unavailable'}</p><p className="text-[10px] text-neutral-500 mt-0.5">{proof?.previewUrl ? proof?.mimeType : 'No authorized preview stream available.'}</p></div>}
          </div>

          {proof?.status === 'rejected' && <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-xs text-rose-800 dark:text-rose-300 space-y-1"><div className="flex items-center gap-1.5 font-bold"><AlertCircle className="w-3.5 h-3.5 text-rose-500" /><span>Rejection Reason:</span></div><p className="text-[11px] font-mono pl-5">{reviewer?.reason || 'Not recorded'}</p></div>}
          {proof?.status === 'verified' && <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2"><ShieldCheck className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" /><div><span className="font-bold">Verified against Merchant Account</span>{reviewer?.actor && <p className="text-[10px] text-emerald-700 dark:text-emerald-400 mt-0.5">Verified by {reviewer.actor}</p>}</div></div>}
        </div>

        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-neutral-100 dark:border-neutral-800">
          <button type="button" disabled={!proof?.downloadUrl} onClick={onDownload} className="w-full sm:w-auto px-4 py-2 text-xs font-semibold rounded-xl bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 disabled:opacity-50 text-neutral-800 dark:text-neutral-200 flex items-center justify-center gap-1.5 transition cursor-pointer"><Download className="w-3.5 h-3.5" /><span>Download Receipt File</span></button>
          <button type="button" onClick={onClose} className="w-full sm:w-auto px-4 py-2 text-xs font-bold rounded-xl bg-neutral-800 text-white hover:bg-neutral-700 cursor-pointer">Close Viewer</button>
        </div>
      </div>
    </div>
  );
};
