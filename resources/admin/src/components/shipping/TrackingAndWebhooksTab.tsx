import React, { useState } from 'react';
import { 
  Radio, 
  Zap, 
  Truck, 
  MapPin, 
  Clock, 
  Code, 
  CheckCircle2, 
  AlertTriangle, 
  RefreshCw, 
  Play,
  ArrowRight,
  Eye
} from 'lucide-react';
import { ShipmentParcel } from '../../types/shipping';

interface TrackingAndWebhooksTabProps {
  parcels: ShipmentParcel[];
  onSimulateWebhook: (parcelId: string, eventType: string, location: string, description: string) => void;
  onOpenTrackingModal: (parcel: ShipmentParcel) => void;
}

export const TrackingAndWebhooksTab: React.FC<TrackingAndWebhooksTabProps> = ({
  parcels,
  onSimulateWebhook,
  onOpenTrackingModal
}) => {
  const [selectedParcelId, setSelectedParcelId] = useState<string>(parcels[0]?.id || '');
  const [customEvent, setCustomEvent] = useState('Out for Delivery');
  const [customLocation, setCustomLocation] = useState('Petaling Jaya Delivery Hub');
  const [customDesc, setCustomDesc] = useState('Assigned to courier rider for final delivery attempt.');

  const selectedParcel = parcels.find(p => p.id === selectedParcelId) || parcels[0];

  const handleSimulate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedParcelId) return;
    onSimulateWebhook(selectedParcelId, customEvent, customLocation, customDesc);
  };

  // Collect all recent tracking events across parcels
  const allEvents = parcels.flatMap(p => 
    (p.trackingEvents || []).map(evt => ({
      ...evt,
      parcelId: p.id,
      orderNumber: p.orderNumber,
      trackingNumber: p.trackingNumber,
      courier: p.courier,
      customerName: p.customerName
    }))
  ).sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
            <Radio className="w-4 h-4 text-purple-600 animate-pulse" />
            Shipment Event Audit Trail
          </h3>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
            Record authorized warehouse milestones now; provider webhooks remain unavailable until an EasyParcel integration is configured.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <span className="px-3 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs font-semibold border border-purple-200 dark:border-purple-800">
            Manual audit mode
          </span>
        </div>
      </div>

      {/* Grid: Webhook Ingest Simulator & Live Event Stream */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Manual tracking event form */}
        <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-1.5">
              <Zap className="w-4 h-4 text-amber-500" />
              Manual Event Entry
            </h4>
            <span className="text-[10px] text-neutral-400 font-mono">append-only audit</span>
          </div>

          <form onSubmit={handleSimulate} className="space-y-3">
            <div>
              <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Target Active Shipment
              </label>
              <select
                value={selectedParcelId}
                onChange={(e) => setSelectedParcelId(e.target.value)}
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 font-mono"
              >
                {parcels.map((p) => (
                  <option key={p.id} value={p.id}>
                    #{p.orderNumber} ({p.trackingNumber || 'Pending AWB'}) - {p.customerName}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Shipment Event Type
              </label>
              <select
                value={customEvent}
                onChange={(e) => {
                  setCustomEvent(e.target.value);
                  if (e.target.value === 'Picked Up') {
                    setCustomLocation('Subang Gateway Hub');
                    setCustomDesc('Courier driver scanned parcel at merchant pickup point.');
                  } else if (e.target.value === 'Out for Delivery') {
                    setCustomLocation('Petaling Jaya Delivery Hub');
                    setCustomDesc('Assigned to delivery courier rider for doorstep handover.');
                  } else if (e.target.value === 'Delivered') {
                    setCustomLocation('Recipient Doorstep');
                    setCustomDesc('Delivered and signed by recipient. Photo uploaded.');
                  } else if (e.target.value === 'Delivery Failed (NITC)') {
                    setCustomLocation('Recipient Address');
                    setCustomDesc('Delivery attempt failed: Recipient unavailable / premise closed.');
                  }
                }}
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
              >
                <option value="Picked Up">PICKED_UP (Driver Collection)</option>
                <option value="Arrived at Sorting Hub">IN_TRANSIT (Sorting Hub Inbound)</option>
                <option value="Out for Delivery">OUT_FOR_DELIVERY (Last-Mile Dispatch)</option>
                <option value="Delivered">DELIVERED (Successful POD)</option>
                <option value="Delivery Failed (NITC)">EXCEPTION (NITC - Delivery Failed)</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Event Location (Hub / City)
              </label>
              <input
                type="text"
                value={customLocation}
                onChange={(e) => setCustomLocation(e.target.value)}
                required
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
              />
            </div>

            <div>
              <label className="block text-[11px] font-semibold text-neutral-700 dark:text-neutral-300 mb-1">
                Event Detail / Status Remarks
              </label>
              <textarea
                rows={2}
                value={customDesc}
                onChange={(e) => setCustomDesc(e.target.value)}
                required
                className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
              />
            </div>

            {/* Quick action buttons */}
            <div className="pt-2">
              <button
                type="submit"
                className="w-full flex items-center justify-center gap-1.5 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-sm transition-colors cursor-pointer"
              >
                <Play className="w-3.5 h-3.5 fill-current" />
                Record Shipment Event
              </button>
            </div>
          </form>

          {/* Quick preset chips */}
          <div className="pt-3 border-t border-neutral-200 dark:border-neutral-800">
            <span className="text-[10px] text-neutral-400 font-semibold uppercase block mb-1.5">
              Quick event presets
            </span>
            <div className="flex flex-wrap gap-1.5">
              <button
                onClick={() => onSimulateWebhook(selectedParcelId, 'Delivered', 'Recipient Doorstep', 'Delivered to resident.')}
                className="px-2 py-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 rounded-lg text-[10px] font-semibold border border-emerald-200 dark:border-emerald-800"
              >
                + Mark Delivered
              </button>
              <button
                onClick={() => onSimulateWebhook(selectedParcelId, 'Delivery Failed (NITC)', 'Recipient Address', 'Recipient premise closed.')}
                className="px-2 py-1 bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 rounded-lg text-[10px] font-semibold border border-rose-200 dark:border-rose-800"
              >
                + Trigger NITC
              </button>
            </div>
          </div>
        </div>

        {/* Live Milestone Feed Stream */}
        <div className="lg:col-span-2 p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                <Clock className="w-4 h-4 text-blue-600" />
                Shipment Event Audit Log
              </h4>
              <span className="text-xs text-neutral-400">
                {allEvents.length} events logged
              </span>
            </div>

            <div className="space-y-3 max-h-[500px] overflow-y-auto pr-1">
              {allEvents.length === 0 ? (
                <div className="p-8 text-center text-xs text-neutral-400">
                  No shipment events have been recorded yet. Record an authorized warehouse milestone on the left.
                </div>
              ) : (
                allEvents.map((evt, idx) => (
                  <div
                    key={idx}
                    className="p-3.5 rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/40 flex items-start justify-between gap-3 hover:border-blue-500/40 transition-colors"
                  >
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 flex items-center justify-center font-bold text-xs shrink-0">
                        <Truck className="w-4 h-4" />
                      </div>

                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-xs text-neutral-900 dark:text-neutral-100">
                            {evt.status}
                          </span>
                          <span className="text-[10px] font-mono px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
                            {evt.trackingNumber || 'AWB-PENDING'}
                          </span>
                        </div>

                        <p className="text-xs text-neutral-600 dark:text-neutral-300 mt-0.5">
                          {evt.description}
                        </p>

                        <div className="flex items-center gap-3 text-[10px] text-neutral-400 mt-1">
                          <span className="flex items-center gap-1">
                            <MapPin className="w-3 h-3" />
                            {evt.location}
                          </span>
                          <span>•</span>
                          <span>Order #{evt.orderNumber} ({evt.customerName})</span>
                        </div>
                      </div>
                    </div>

                    <div className="text-right shrink-0">
                      <span className="text-[10px] font-mono text-neutral-400 block">
                        {evt.timestamp}
                      </span>
                      {selectedParcel && (
                        <button
                          onClick={() => onOpenTrackingModal(selectedParcel)}
                          className="text-[11px] text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-flex items-center gap-0.5 font-medium"
                        >
                          <Eye className="w-3 h-3" /> View Parcel
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          <div className="mt-4 pt-3 border-t border-neutral-200 dark:border-neutral-800 text-[11px] text-neutral-500 dark:text-neutral-400 flex items-center justify-between">
            <span>Provider webhook validation: <strong>Not connected</strong></span>
            <span className="font-mono">Source: manual warehouse audit</span>
          </div>
        </div>
      </div>
    </div>
  );
};
