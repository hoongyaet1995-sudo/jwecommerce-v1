import React from 'react';
import { Lock, Server, Settings, Zap } from 'lucide-react';
import { CourierConfig } from '../../types/shipping';

interface CourierConfigTabProps {
  couriers: CourierConfig[];
  onToggleCourier: (id: string, isEnabled: boolean) => void;
  onOpenConfigModal: (courier: CourierConfig) => void;
  onPingCourier: (id: string) => Promise<number>;
}

export const CourierConfigTab: React.FC<CourierConfigTabProps> = ({
  couriers,
  onToggleCourier,
  onOpenConfigModal,
  onPingCourier
}) => {
  const easyParcel = couriers.find((courier) => courier.courierCode === 'easyparcel');
  const gatewayHealthy = easyParcel?.status === 'connected';

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h3 className="text-base font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
            <Server className="w-4 h-4 text-blue-600" />
            Malaysian Courier API Integrations & Gateway Hub
          </h3>
          <p className="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 max-w-2xl">
            Directly connect your store's merchant accounts for J&T Express, Ninja Van, Pos Malaysia, Flash Express, DHL eCommerce, and EasyParcel. Supports Sandbox testing and Production cutover.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <span className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border ${
            gatewayHealthy
              ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
              : 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'
          }`}>
            <span className={`w-2 h-2 rounded-full ${gatewayHealthy ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'}`} />
            {gatewayHealthy ? 'API Gateway Healthy' : 'Gateway Configuration Required'}
          </span>
        </div>
      </div>

      {/* Courier Gateway Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {couriers.map((courier) => {
          const supportsCod = courier.supportsCOD ?? (courier as any).features?.supportsCod ?? false;
          const supportsEM = courier.supportsEastMalaysia ?? (courier as any).features?.supportsEastMalaysia ?? false;
          const autoQueue = courier.autoRetryQueue ?? (courier as any).features?.autoAwbGeneration ?? false;

          return (
            <div
              key={courier.id}
              className={`p-5 rounded-2xl border flex flex-col justify-between transition-all bg-white dark:bg-neutral-900 shadow-sm ${
                courier.isEnabled
                  ? 'border-neutral-200 dark:border-neutral-800'
                  : 'border-neutral-200/60 dark:border-neutral-800/50 opacity-70'
              }`}
            >
              {/* Header */}
              <div>
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center font-black text-sm text-neutral-800 dark:text-neutral-200">
                      {(courier.name || courier.displayName || 'COU').substring(0, 3).toUpperCase()}
                    </div>
                    <div>
                      <h4 className="text-sm font-bold text-neutral-900 dark:text-neutral-100">
                        {courier.displayName}
                      </h4>
                      <div className="flex items-center gap-1.5 mt-0.5">
                        <span className={`px-1.5 py-0.2 text-[9px] uppercase font-bold rounded ${
                          courier.environment === 'production'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                        }`}>
                          {courier.environment || 'production'}
                        </span>
                        {courier.lastPingLatency && (
                          <span className="text-[10px] text-neutral-400 font-mono">
                            {courier.lastPingLatency}ms
                          </span>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Toggle switch */}
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={courier.isEnabled}
                      onChange={(e) => onToggleCourier(courier.id, e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-neutral-200 peer-focus:outline-none rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-neutral-600 peer-checked:bg-blue-600"></div>
                  </label>
                </div>

                {/* Features Badges */}
                <div className="mt-4 space-y-1.5 text-xs text-neutral-600 dark:text-neutral-400">
                  <div className="flex items-center justify-between text-[11px]">
                    <span>COD Support:</span>
                    <span className={supportsCod ? 'text-emerald-600 font-medium' : 'text-neutral-400'}>
                      {supportsCod ? '✓ Enabled' : '✗ No'}
                    </span>
                  </div>
                  <div className="flex items-center justify-between text-[11px]">
                    <span>East Malaysia (Air):</span>
                    <span className={supportsEM ? 'text-emerald-600 font-medium' : 'text-neutral-400'}>
                      {supportsEM ? '✓ Supported' : '✗ West MY Only'}
                    </span>
                  </div>
                  <div className="flex items-center justify-between text-[11px]">
                    <span>Auto-Retry Queue:</span>
                    <span className={autoQueue ? 'text-blue-600 font-medium' : 'text-neutral-400'}>
                      {autoQueue ? '✓ Active' : 'Manual'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Footer action */}
              <div className="mt-5 pt-3 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between gap-2">
                <button
                  onClick={() => onPingCourier(courier.id)}
                  className="p-2 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
                  title="Ping Courier Handshake"
                >
                  <Zap className="w-4 h-4 text-amber-500" />
                </button>

                <button
                  onClick={() => onOpenConfigModal(courier)}
                  className="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 rounded-xl transition-colors cursor-pointer"
                >
                  <Settings className="w-3.5 h-3.5" />
                  Configure Credentials
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Developer Reference for Malaysian Courier Protocol Specifications */}
      <div className="p-5 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30 space-y-3">
        <h4 className="text-xs font-bold text-neutral-900 dark:text-neutral-100 uppercase tracking-wider flex items-center gap-1.5">
          <Lock className="w-3.5 h-3.5 text-blue-600" />
          Technical Specifications: Malaysian Courier APIs
        </h4>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs text-neutral-600 dark:text-neutral-400">
          <div className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
            <div className="font-bold text-neutral-900 dark:text-neutral-100">J&T Express Malaysia</div>
            <p className="text-[11px]">
              Requires Base64-MD5 data digest signature algorithm (<code>data_digest = base64(md5(content + secretKey))</code>). Webhook events push via JSON POST.
            </p>
          </div>
          <div className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
            <div className="font-bold text-neutral-900 dark:text-neutral-100">Ninja Van Malaysia</div>
            <p className="text-[11px]">
              Uses OAuth2 Client Credentials grant (<code>/oauth/access_token</code>) with Bearer token authentication. Real-time HMAC-SHA256 signature verification for webhooks.
            </p>
          </div>
          <div className="p-3 bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 space-y-1">
            <div className="font-bold text-neutral-900 dark:text-neutral-100">Pos Malaysia / Pos Laju</div>
            <p className="text-[11px]">
              Connects to Pos Malaysia Enterprise Consignment Gateway with XML/JSON hybrid payloads. Direct support for East Malaysia (Sabah & Sarawak) special hub routing.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
