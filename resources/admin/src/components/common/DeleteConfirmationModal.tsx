import React, { useEffect, useRef } from 'react';
import { AlertTriangle, ShieldAlert, Trash2, X } from 'lucide-react';

export interface DeleteConfirmationModalProps {
  isOpen: boolean;
  title?: string;
  itemType?: string;
  itemName?: string;
  itemDetails?: string;
  warningMessage?: string;
  impactItems?: string[];
  confirmButtonText?: string;
  cancelButtonText?: string;
  pending?: boolean;
  onConfirm: () => void | Promise<void>;
  onClose: () => void;
}

export const DeleteConfirmationModal: React.FC<DeleteConfirmationModalProps> = ({
  isOpen,
  title,
  itemType = 'Item',
  itemName,
  itemDetails,
  warningMessage,
  impactItems,
  confirmButtonText = 'Yes, Delete Permanently',
  cancelButtonText = 'Cancel & Keep',
  pending = false,
  onConfirm,
  onClose,
}) => {
  const cancelButton = useRef<HTMLButtonElement>(null);
  const returnFocus = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!isOpen) {
      returnFocus.current?.focus();
      return;
    }

    returnFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    cancelButton.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !pending) onClose();
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose, pending]);

  if (!isOpen) return null;

  const resolvedTitle = title || `Delete ${itemType}?`;
  const resolvedImpacts = impactItems?.length ? impactItems : [
    'This record will be permanently deleted from the database.',
    'Any storefront associations or live links pointing here may become inactive.',
    'This operation cannot be automatically reversed.',
  ];

  return (
    <div
      className="fixed inset-0 z-100 flex items-center justify-center p-4 bg-black/75 backdrop-blur-md animate-in fade-in duration-200"
      onClick={() => { if (!pending) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-confirmation-title"
    >
      <div
        onClick={(event) => event.stopPropagation()}
        className="w-full max-w-lg bg-white dark:bg-neutral-900 border border-rose-200 dark:border-rose-900/60 rounded-3xl p-6 sm:p-7 shadow-2xl shadow-rose-950/20 text-neutral-900 dark:text-white relative overflow-hidden animate-in zoom-in-95 duration-200"
      >
        <div className="absolute top-0 left-0 right-0 h-1.5 bg-linear-to-r from-rose-500 via-rose-600 to-amber-500" />

        <div className="flex items-start justify-between gap-4 mb-4">
          <div className="flex items-center gap-3">
            <div className="w-11 h-11 rounded-2xl bg-rose-100 dark:bg-rose-950/80 border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0 shadow-inner">
              <Trash2 className="w-5 h-5 animate-pulse" />
            </div>
            <div>
              <span className="text-[10px] font-extrabold uppercase tracking-widest text-rose-600 dark:text-rose-400 block">Destructive Action</span>
              <h3 id="delete-confirmation-title" className="text-base sm:text-lg font-bold text-neutral-900 dark:text-white leading-tight">{resolvedTitle}</h3>
            </div>
          </div>
          <button type="button" onClick={onClose} disabled={pending} className="p-1.5 rounded-xl text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer disabled:opacity-50" aria-label="Close dialog">
            <X className="w-4 h-4" />
          </button>
        </div>

        {itemName && (
          <div className="mb-4 p-3.5 rounded-2xl bg-neutral-50 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700/80 flex items-center justify-between gap-3">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-neutral-200/70 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">{itemType}</span>
                <p className="text-xs sm:text-sm font-bold text-neutral-900 dark:text-white truncate">{itemName}</p>
              </div>
              {itemDetails && <p className="text-[11px] font-mono text-neutral-500 dark:text-neutral-400 mt-1 truncate">{itemDetails}</p>}
            </div>
            <div className="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-500 shrink-0"><AlertTriangle className="w-4 h-4" /></div>
          </div>
        )}

        <div className="space-y-3 mb-6">
          <p className="text-xs sm:text-sm text-neutral-600 dark:text-neutral-300 leading-relaxed">{warningMessage || `Are you sure you want to remove this ${itemType.toLowerCase()}? Please confirm before proceeding.`}</p>
          <div className="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/40 space-y-1.5">
            <div className="flex items-center gap-1.5 text-[11px] font-bold text-rose-800 dark:text-rose-300"><ShieldAlert className="w-3.5 h-3.5" /><span>Consequences of Deletion:</span></div>
            <ul className="space-y-1 text-[11px] text-rose-700 dark:text-rose-300/90 pl-1">
              {resolvedImpacts.map((impact, index) => <li key={index} className="flex items-start gap-1.5"><span className="text-rose-500 font-bold">•</span><span>{impact}</span></li>)}
            </ul>
          </div>
        </div>

        <div className="flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 pt-2 border-t border-neutral-100 dark:border-neutral-800">
          <button ref={cancelButton} type="button" onClick={onClose} disabled={pending} className="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-700/80 transition-colors cursor-pointer disabled:opacity-50">{cancelButtonText}</button>
          <button type="button" onClick={() => void onConfirm()} disabled={pending} className="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-xs font-bold shadow-lg shadow-rose-600/25 flex items-center justify-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01] disabled:opacity-50 disabled:hover:scale-100">
            <Trash2 className="w-3.5 h-3.5" /><span>{pending ? 'Deleting…' : confirmButtonText}</span>
          </button>
        </div>
      </div>
    </div>
  );
};
