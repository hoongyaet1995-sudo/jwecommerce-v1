import React from 'react';
import { Download, FileText, Printer, ShieldCheck, X } from 'lucide-react';
import { ShipmentParcel } from '../../types/shipping';

interface ThermalAWBModalProps {
  parcel: ShipmentParcel | null;
  onClose: () => void;
}

const labelUrl = (parcelId: string, download = false): string =>
  `/admin/api/shipping/shipments/${encodeURIComponent(parcelId)}/label${download ? '/download' : ''}`;

export const ThermalAWBModal: React.FC<ThermalAWBModalProps> = ({ parcel, onClose }) => {
  if (!parcel) return null;

  const hasOfficialLabel = parcel.source === 'easyparcel' && parcel.hasLabel;
  const previewUrl = labelUrl(parcel.id);
  const downloadUrl = labelUrl(parcel.id, true);
  const unavailableMessage = parcel.source === 'manual'
    ? 'Manual dispatch does not have a provider AWB.'
    : 'Official EasyParcel AWB is still pending. Refresh the provider status or reconcile the shipment before printing.';

  const handlePrint = () => {
    if (!hasOfficialLabel) return;
    window.open(previewUrl, '_blank', 'noopener,noreferrer');
  };

  return (
    <div id="thermal-awb-modal-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-3xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <Printer className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                  4x6&quot; Thermal Airway Bill (Consignment Note)
                </h3>
                <span className="px-2 py-0.5 text-xs font-mono font-medium rounded-md bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                  Official PDF
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Order #{parcel.orderNumber} • Tracking: {parcel.trackingNumber || 'Pending Generation'}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handlePrint}
              disabled={!hasOfficialLabel}
              title={hasOfficialLabel ? 'Open the official EasyParcel PDF in the browser print viewer' : unavailableMessage}
              className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-neutral-300 dark:disabled:bg-neutral-700 disabled:text-neutral-500 disabled:cursor-not-allowed rounded-lg transition-colors shadow-sm cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              Print Thermal AWB
            </button>
            <button
              type="button"
              onClick={onClose}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              aria-label="Close AWB preview"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        <div className="flex-1 min-h-[520px] overflow-hidden bg-neutral-100 dark:bg-neutral-950">
          {hasOfficialLabel ? (
            <iframe
              title="Official EasyParcel AWB PDF"
              src={previewUrl}
              className="w-full h-[68vh] min-h-[520px] border-0 bg-white"
            />
          ) : (
            <div className="h-[520px] flex items-center justify-center p-8">
              <div className="max-w-md text-center space-y-3 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm">
                <div className="w-12 h-12 mx-auto rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                  <FileText className="w-6 h-6" />
                </div>
                <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">Official AWB unavailable</h4>
                <p className="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">{unavailableMessage}</p>
              </div>
            </div>
          )}
        </div>

        <div className="px-6 py-3.5 border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between gap-3">
          <div className="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-1.5">
            <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
            {hasOfficialLabel
              ? 'The official EasyParcel PDF is streamed through an authorized server endpoint.'
              : unavailableMessage}
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
            >
              Close
            </button>
            {hasOfficialLabel ? (
              <a
                href={downloadUrl}
                className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors cursor-pointer"
              >
                <Download className="w-3.5 h-3.5" />
                Download AWB PDF
              </a>
            ) : (
              <button
                type="button"
                disabled
                title={unavailableMessage}
                className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium rounded-lg bg-neutral-300 dark:bg-neutral-700 text-neutral-500 cursor-not-allowed"
              >
                <Download className="w-3.5 h-3.5" />
                Download AWB PDF
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
