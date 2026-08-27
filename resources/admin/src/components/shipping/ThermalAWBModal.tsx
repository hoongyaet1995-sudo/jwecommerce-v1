import React, { useState } from 'react';
import { 
  X, 
  Printer, 
  Download, 
  CheckCircle2, 
  Plane, 
  ShieldAlert, 
  Package, 
  QrCode, 
  Truck,
  Building,
  User,
  Phone,
  MapPin,
  Calendar,
  AlertTriangle
} from 'lucide-react';
import { ShipmentParcel } from '../../types/shipping';

interface ThermalAWBModalProps {
  parcel: ShipmentParcel | null;
  onClose: () => void;
  onPrint?: (parcelId: string) => void;
}

export const ThermalAWBModal: React.FC<ThermalAWBModalProps> = ({
  parcel,
  onClose,
  onPrint
}) => {
  const [includePackingSlip, setIncludePackingSlip] = useState(true);
  const [isPrinting, setIsPrinting] = useState(false);

  if (!parcel) return null;

  const isEastMalaysia = parcel.isEastMalaysiaAirRestricted;
  const isCOD = parcel.paymentMode === 'cod';
  // ShipmentParcel is the redacted collection DTO. A separately authorized label/detail workflow is out of scope.
  const canPrint = false;

  const handlePrint = () => {
    if (!canPrint) return;

    setIsPrinting(true);
    setTimeout(() => {
      setIsPrinting(false);
      if (onPrint) onPrint(parcel.id);
      window.print();
    }, 400);
  };

  const getCourierColor = (courier: string) => {
    switch (courier) {
      case 'jnt': return 'bg-red-600 text-white';
      case 'ninjavan': return 'bg-rose-700 text-white';
      case 'posmalaysia': return 'bg-amber-600 text-white';
      case 'easyparcel': return 'bg-pink-600 text-white';
      case 'flashexpress': return 'bg-yellow-500 text-black';
      case 'dhl': return 'bg-yellow-400 text-red-700 font-bold';
      default: return 'bg-gray-800 text-white';
    }
  };

  return (
    <div id="thermal-awb-modal-overlay" className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto backdrop-blur-sm">
      <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl max-w-3xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/80">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
              <Printer className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                  4x6" Thermal Airway Bill (Consignment Note)
                </h3>
                <span className="px-2 py-0.5 text-xs font-mono font-medium rounded-md bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                  100 x 150 mm
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Order #{parcel.orderNumber} • Tracking: {parcel.trackingNumber || 'Pending Generation'}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <label className="hidden sm:flex items-center gap-2 text-xs text-neutral-600 dark:text-neutral-300 cursor-pointer">
              <input 
                type="checkbox" 
                checked={includePackingSlip}
                onChange={(e) => setIncludePackingSlip(e.target.checked)}
                className="rounded text-blue-600 focus:ring-blue-500"
              />
              Include Item Packing Slip
            </label>
            <button
              onClick={handlePrint}
              disabled={isPrinting || !canPrint}
              title="Authorized recipient and label details are required before printing"
              className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              {isPrinting ? 'Preparing...' : 'Print Thermal AWB'}
            </button>
            <button
              onClick={onClose}
              className="p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Scrollable Preview Area */}
        <div className="flex-1 overflow-y-auto p-6 bg-neutral-100 dark:bg-neutral-950 flex justify-center">
          {/* Thermal Paper Simulation (4" x 6" standard A6 aspect) */}
          <div 
            id="printable-thermal-awb" 
            className="w-full max-w-[420px] bg-white text-black font-sans border-2 border-dashed border-neutral-300 dark:border-neutral-700 shadow-xl rounded-sm p-4 text-[11px] leading-tight select-text print:border-none print:shadow-none print:m-0 print:p-2"
          >
            {/* Top Bar: Courier Brand & Routing Code */}
            <div className="flex items-center justify-between border-b-2 border-black pb-2 mb-2">
              <div className="flex items-center gap-2">
                <span className={`px-2.5 py-1 text-xs uppercase font-black tracking-wider rounded ${getCourierColor(parcel.courier)}`}>
                  {parcel.courier === 'posmalaysia' ? 'POS MALAYSIA' : (parcel.courier || 'COURIER').toUpperCase()}
                </span>
                <span className="text-[10px] font-semibold text-gray-700 uppercase">
                  {parcel.serviceType || 'Standard Express'}
                </span>
              </div>
              <div className="text-right">
                <div className="text-xl font-black font-mono tracking-tight text-black">
                  {parcel.routingSortCode || 'KUL-01'}
                </div>
                <div className="text-[9px] font-bold text-gray-600">SORT HUB CODE</div>
              </div>
            </div>

            {/* Destination Highlight Box */}
            <div className="bg-gray-100 border border-black p-2 mb-2 flex items-center justify-between">
              <div>
                <div className="text-[9px] uppercase tracking-wider font-bold text-gray-600">DESTINATION POSTCODE & STATE</div>
                <div className="text-lg font-black font-mono text-black">
                  RECIPIENT DETAILS RESTRICTED
                </div>
              </div>
              <div className="text-right">
                <span className={`px-2 py-0.5 text-[10px] font-black uppercase rounded ${
                  isEastMalaysia ? 'bg-purple-900 text-white' : 'bg-black text-white'
                }`}>
                  {isEastMalaysia ? 'AIR RESTRICTION FLAGGED' : 'REGION RESTRICTED'}
                </span>
                {isCOD && (
                  <div className="mt-1 text-xs font-black text-red-600 bg-red-100 border border-red-500 px-1 py-0.5 rounded text-center">
                    COD: RM {parcel.codAmount?.toFixed(2) || '0.00'}
                  </div>
                )}
              </div>
            </div>

            {/* Barcode Visualizer */}
            <div className="border border-black py-2.5 px-2 text-center mb-2 bg-white flex flex-col items-center justify-center">
              {/* Simulated crisp Barcode lines */}
              <div className="h-12 w-full max-w-[340px] flex items-stretch justify-center gap-[2px] bg-white px-2 py-1 overflow-hidden">
                {Array.from({ length: 55 }).map((_, i) => (
                  <div 
                    key={i} 
                    className={`bg-black ${
                      i % 7 === 0 ? 'w-[3px]' : i % 5 === 0 ? 'w-[2px]' : i % 3 === 0 ? 'w-[1px]' : i % 2 === 0 ? 'w-[2.5px]' : 'w-[1.5px]'
                    } ${i % 11 === 0 ? 'opacity-0' : 'opacity-100'}`}
                  />
                ))}
              </div>
              <div className="font-mono text-sm font-black tracking-widest mt-1 text-black">
                {parcel.trackingNumber || 'MY-JNT-9882736192-KUL'}
              </div>
            </div>

            {/* Consignee & Sender Grid */}
            <div className="border-2 border-black grid grid-cols-2 text-[10px] mb-2 divide-x border-black">
              {/* Sender Details */}
              <div className="p-2 bg-gray-50/50">
                <div className="font-bold text-[9px] text-gray-500 uppercase flex items-center gap-1 mb-1">
                  <Building className="w-3 h-3 text-gray-700" /> SENDER (FROM)
                </div>
                <div className="font-bold text-black text-[11px]">Aurora Commerce Hub HQ</div>
                <div className="text-gray-700 text-[10px] mt-0.5 leading-snug">
                  No. 18, Jalan TP 5, Taman Perindustrian UEP, 47600 Subang Jaya, Selangor, Malaysia
                </div>
                <div className="text-[10px] font-mono mt-1 font-semibold text-black">Tel: +60 3-8023 9988</div>
              </div>

              {/* Recipient Details */}
              <div className="p-2 bg-white">
                <div className="font-bold text-[9px] text-gray-500 uppercase flex items-center gap-1 mb-1">
                  <User className="w-3 h-3 text-gray-700" /> RECIPIENT (TO)
                </div>
                <div className="font-black text-black text-[11px]">{parcel.customerName}</div>
                <div className="text-gray-800 text-[10px] mt-0.5 leading-snug font-medium">
                  Recipient address restricted in shipment list
                </div>
                <div className="font-black font-mono text-[11px] text-black mt-0.5">
                  Open the authorized order workflow for delivery details
                </div>
                <div className="text-[10px] font-mono font-bold text-black mt-1">Tel: Restricted</div>
              </div>
            </div>

            {/* Package Specifications & QR Box */}
            <div className="border border-black p-2 mb-2 flex items-center justify-between text-[10px]">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <span className="text-gray-600">Weight:</span>
                  <span className="font-bold font-mono text-black">{parcel.totalWeightKg === null ? 'Not configured' : `${parcel.totalWeightKg} kg`}</span>
                  <span className="text-gray-400">|</span>
                  <span className="text-gray-600">Vol:</span>
                  <span className="font-bold font-mono text-black">{parcel.volumetricWeightKg === null ? 'Not configured' : `${parcel.volumetricWeightKg} kg`}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-gray-600">Dim:</span>
                  <span className="font-mono text-black">
                    {parcel.dimensionsCm ? `${parcel.dimensionsCm.length}x${parcel.dimensionsCm.width}x${parcel.dimensionsCm.height} cm` : 'Not configured'}
                  </span>
                </div>
                <div className="text-[9px] text-gray-500">
                  Order Date: {new Date(parcel.createdAt).toLocaleDateString('en-MY')}
                </div>
              </div>

              <div className="flex flex-col items-center justify-center p-1 border border-black bg-gray-50">
                <QrCode className="w-10 h-10 text-black" />
                <span className="text-[7px] font-mono font-bold text-black mt-0.5">SCAN-AWB</span>
              </div>
            </div>

            {/* East Malaysia Air Customs Declaration (CN22 mandatory) */}
            {isEastMalaysia && (
              <div className="border-2 border-red-600 bg-red-50/40 p-2 mb-2 text-[9px]">
                <div className="flex items-center gap-1 font-bold text-red-700 uppercase mb-0.5">
                  <Plane className="w-3 h-3 text-red-600" />
                  EAST MALAYSIA AIR FREIGHT / CUSTOMS DECLARATION (CN22)
                </div>
                <div className="text-gray-700 leading-tight">
                  Contents and declared value must be confirmed before a courier label is issued.
                </div>
              </div>
            )}

            {/* Optional Packing Slip Summary */}
            {includePackingSlip && (
              <div className="border-t-2 border-black pt-2 mt-2">
                <div className="text-[9px] font-bold text-gray-700 uppercase mb-1 flex items-center justify-between">
                  <span>PACKING ITEM MANIFEST ({parcel.items.length} SKUs)</span>
                  <span className="font-mono">Order #{parcel.orderNumber}</span>
                </div>
                <table className="w-full text-[9px] border-collapse">
                  <thead>
                    <tr className="border-b border-gray-300 text-left text-gray-500 font-semibold">
                      <th className="pb-1">SKU / Item</th>
                      <th className="pb-1 text-center">Qty</th>
                      <th className="pb-1 text-right">Price</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {parcel.items.map((it, idx) => (
                      <tr key={idx}>
                        <td className="py-1 pr-1 font-medium text-black">
                          <div>{it.name}</div>
                          <div className="text-[8px] font-mono text-gray-500">{it.sku}</div>
                        </td>
                        <td className="py-1 text-center font-bold text-black font-mono">x{it.qty}</td>
                        <td className="py-1 text-right font-mono text-gray-800">RM {it.unitPrice.toFixed(2)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {/* Bottom Bar: Merchant Signature & Thermal Cutter Mark */}
            <div className="mt-3 pt-2 border-t border-dashed border-gray-400 flex items-center justify-between text-[8px] text-gray-500">
              <span>Packed by: Aurora WMS Engine v2.4</span>
              <span>Courier Signature / Proof of Delivery</span>
            </div>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="px-6 py-3.5 border-t border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 flex items-center justify-between">
          <div className="text-xs text-neutral-500 dark:text-neutral-400">
            Printing unavailable: authorized recipient and label details are not included in shipment-list responses.
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
            >
              Close
            </button>
            <button
              onClick={handlePrint}
              disabled={isPrinting || !canPrint}
              title="Authorized recipient and label details are required before printing"
              className="flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              {isPrinting ? 'Sending to Spooler...' : 'Print Waybill'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
