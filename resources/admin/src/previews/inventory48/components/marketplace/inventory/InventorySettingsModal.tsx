import React, { useState, useMemo } from 'react';
import {
  X,
  Settings,
  ShieldAlert,
  ShieldCheck,
  AlertTriangle,
  CheckCircle2,
  Layers,
  Store,
  ArrowRight,
  RefreshCw,
  Info,
  ExternalLink,
  ChevronRight,
  SlidersHorizontal,
  Boxes
} from 'lucide-react';
import { MarketplaceInventoryItem } from '../../../types';
import { InventoryStoreChannel, PreSwitchSafetyBlocker } from './inventoryTypes';
import { MarketplaceLogo } from '../MarketplaceLogo';

interface InventorySettingsModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentMode: 'shared' | 'independent';
  stores: InventoryStoreChannel[];
  inventory: MarketplaceInventoryItem[];
  onSwitchMode: (
    newMode: 'shared' | 'independent',
    staffName: string,
    reason: string,
    allocations?: Record<string, Record<string, number>>
  ) => Promise<{ success: boolean; error?: string }>;
  onToggleStoreManagement: (storeId: string, enable: boolean) => void;
  onSelectSkuForCount?: (sku: string) => void;
}

export const InventorySettingsModal: React.FC<InventorySettingsModalProps> = ({
  isOpen,
  onClose,
  currentMode,
  stores,
  inventory,
  onSwitchMode,
  onToggleStoreManagement,
  onSelectSkuForCount
}) => {
  const [activeTab, setActiveTab] = useState<'mode' | 'stores'>('mode');

  // Mode Switch Workflow: 'view' | 'checklist' | 'allocation_review' | 'confirm'
  const [switchFlowStep, setSwitchFlowStep] = useState<'view' | 'checklist' | 'allocation_review' | 'confirm'>('view');
  const [targetMode, setTargetMode] = useState<'shared' | 'independent'>(currentMode === 'shared' ? 'independent' : 'shared');
  const [adminName, setAdminName] = useState('Alexander (Super Admin)');
  const [switchReason, setSwitchReason] = useState('Strategic business policy realignment');
  const [isExecutingSwitch, setIsExecutingSwitch] = useState(false);

  // Store takeover modal state
  const [takeoverStoreTarget, setTakeoverStoreTarget] = useState<InventoryStoreChannel | null>(null);
  const [takeoverStep, setTakeoverStep] = useState<'checklist' | 'preview' | 'confirm'>('checklist');

  // Allocation distribution state for Shared -> Independent switch
  // sku -> { storeId: allocatedQty }
  const [allocations, setAllocations] = useState<Record<string, Record<string, number>>>({});

  // Reset when opened
  React.useEffect(() => {
    if (isOpen) {
      setSwitchFlowStep('view');
      setTargetMode(currentMode === 'shared' ? 'independent' : 'shared');
      setTakeoverStoreTarget(null);
    }
  }, [isOpen, currentMode]);

  // Safety Blockers Check
  const safetyBlockers: PreSwitchSafetyBlocker[] = useMemo(() => {
    const blockers: PreSwitchSafetyBlocker[] = [];
    const safeInv = inventory || [];

    // 1. Check for uncounted SKUs
    const uncounted = safeInv.filter(i => i.physicalStockStatus === 'unknown' || i.totalPhysicalStock === null);
    if (uncounted.length > 0) {
      uncounted.forEach(item => {
        blockers.push({
          sku: item.masterSku,
          productName: item.productName,
          issueType: 'uncounted',
          title: `Unverified Initial Count (${item.masterSku})`,
          description: 'This SKU has not completed its initial verified physical count. Stock numbers must be verified before changing operating mode.',
          actionLabel: 'Perform Count'
        });
      });
    }

    return blockers;
  }, [inventory]);

  if (!isOpen) return null;

  const safeStores = stores || [];
  const safeInventory = inventory || [];

  // Initialize default balanced allocation when entering allocation step
  const initializeAllocations = () => {
    const activeStores = safeStores.filter(s => !s.isComingSoon && s.connectionState === 'connected');
    const newAlloc: Record<string, Record<string, number>> = {};

    safeInventory.forEach(item => {
      const total = item.availableStock ?? item.totalPhysicalStock ?? 0;
      newAlloc[item.masterSku] = {};

      if (activeStores.length > 0) {
        const perStore = Math.floor(total / activeStores.length);
        const remainder = total % activeStores.length;

        activeStores.forEach((st, idx) => {
          newAlloc[item.masterSku][st.id] = perStore + (idx === 0 ? remainder : 0);
        });
      }
    });

    setAllocations(newAlloc);
  };

  const handleStartSwitchFlow = () => {
    setTargetMode(currentMode === 'shared' ? 'independent' : 'shared');
    setSwitchFlowStep('checklist');
  };

  const handleProceedFromChecklist = () => {
    if (targetMode === 'independent') {
      initializeAllocations();
      setSwitchFlowStep('allocation_review');
    } else {
      setSwitchFlowStep('confirm');
    }
  };

  const handleExecuteModeSwitch = async () => {
    setIsExecutingSwitch(true);
    try {
      const res = await onSwitchMode(
        targetMode,
        adminName,
        switchReason,
        targetMode === 'independent' ? allocations : undefined
      );
      if (res.success) {
        setSwitchFlowStep('view');
        onClose();
      }
    } finally {
      setIsExecutingSwitch(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-neutral-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150">
      <div 
        id="inventory-settings-modal"
        className="bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-3xl overflow-hidden flex flex-col max-h-[92vh]"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-neutral-100 dark:border-neutral-800 flex items-center justify-between bg-neutral-50/50 dark:bg-neutral-900/50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 flex items-center justify-center">
              <Settings className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  Inventory Master Settings
                </h3>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 border border-neutral-200 dark:border-neutral-700">
                  Admin Only
                </span>
              </div>
              <p className="text-xs text-neutral-500 dark:text-neutral-400">
                Global stock policy enforcement and store inventory takeover configurations.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="p-2 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tab Navigation */}
        <div className="px-6 pt-3 border-b border-neutral-100 dark:border-neutral-800 flex items-center gap-4">
          <button
            type="button"
            onClick={() => setActiveTab('mode')}
            className={`pb-3 text-xs font-bold transition-colors cursor-pointer border-b-2 flex items-center gap-2 ${
              activeTab === 'mode'
                ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            <Layers className="w-4 h-4" />
            <span>Global Stock Mode</span>
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('stores')}
            className={`pb-3 text-xs font-bold transition-colors cursor-pointer border-b-2 flex items-center gap-2 ${
              activeTab === 'stores'
                ? 'border-neutral-900 text-neutral-900 dark:border-white dark:text-white'
                : 'border-transparent text-neutral-500 hover:text-neutral-900 dark:hover:text-white'
            }`}
          >
            <Store className="w-4 h-4" />
            <span>Store Inventory Management</span>
          </button>
        </div>

        {/* Modal Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* TAB 1: GLOBAL STOCK MODE */}
          {activeTab === 'mode' && (
            <div className="space-y-6">
              {switchFlowStep === 'view' && (
                <>
                  {/* Current Active Mode Card */}
                  <div className="p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-200">
                        Current Operating Policy
                      </span>
                      <span className="text-xs font-bold px-2.5 py-1 rounded-full bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 shadow-xs">
                        {currentMode === 'shared' ? 'Shared Stock' : 'Independent Stock'}
                      </span>
                    </div>

                    <p className="text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
                      {currentMode === 'shared'
                        ? 'All connected stores sell from a single central pool of Available stock. Adding new stores does not increase physical stock. When an item sells on any channel, the shared available balance immediately updates across all platforms.'
                        : 'Each store maintains an isolated inventory account. Stock is explicitly allocated per store and transferred via Store Transfers. Joining a new store starts its balance at 0 or Not Counted, without auto-duplicating physical goods.'}
                    </p>

                    <div className="pt-2 flex items-center justify-between border-t border-neutral-200 dark:border-neutral-700 text-[11px] text-neutral-700 dark:text-neutral-300 font-medium">
                      <span>Managed centrally by Admin. Policy applies strictly to all connected stores.</span>
                      <button
                        type="button"
                        onClick={handleStartSwitchFlow}
                        className="px-4 py-1.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 font-bold transition cursor-pointer"
                      >
                        Switch to {currentMode === 'shared' ? 'Independent Stock' : 'Shared Stock'} Mode
                      </button>
                    </div>
                  </div>

                  {/* Mode Comparison Grid */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div className={`p-4 rounded-xl border space-y-2 ${
                      currentMode === 'shared'
                        ? 'border-neutral-900 dark:border-neutral-400 bg-neutral-100/60 dark:bg-neutral-800/80 shadow-xs'
                        : 'border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30'
                    }`}>
                      <div className="font-bold text-neutral-900 dark:text-white flex items-center justify-between">
                        <span>Shared Stock</span>
                        {currentMode === 'shared' && <span className="text-[10px] text-neutral-900 dark:text-white font-bold bg-neutral-200 dark:bg-neutral-700 px-1.5 py-0.5 rounded">Active</span>}
                      </div>
                      <ul className="space-y-1.5 text-neutral-600 dark:text-neutral-400">
                        <li>• 1 shared pool per SKU for all channels.</li>
                        <li>• Platform stock numbers are verified references, not separate inventory.</li>
                        <li>• Ideal for centralized fulfillment hubs.</li>
                        <li>• No inter-store transfers required.</li>
                      </ul>
                    </div>

                    <div className={`p-4 rounded-xl border space-y-2 ${
                      currentMode === 'independent'
                        ? 'border-neutral-900 dark:border-neutral-400 bg-neutral-100/60 dark:bg-neutral-800/80 shadow-xs'
                        : 'border-neutral-200 dark:border-neutral-800 bg-neutral-50/50 dark:bg-neutral-800/30'
                    }`}>
                      <div className="font-bold text-neutral-900 dark:text-white flex items-center justify-between">
                        <span>Independent Stock</span>
                        {currentMode === 'independent' && <span className="text-[10px] text-neutral-900 dark:text-white font-bold bg-neutral-200 dark:bg-neutral-700 px-1.5 py-0.5 rounded">Active</span>}
                      </div>
                      <ul className="space-y-1.5 text-neutral-600 dark:text-neutral-400">
                        <li>• Isolated stock accounts per connected store.</li>
                        <li>• Stock can be rebalanced via Store Stock Transfers.</li>
                        <li>• Prevents one store's volume from starving another store.</li>
                        <li>• Enables the "Stock Transfers" tab for staff.</li>
                      </ul>
                    </div>
                  </div>
                </>
              )}

              {/* SWITCH STEP 1: SAFETY CHECKLIST */}
              {switchFlowStep === 'checklist' && (
                <div className="space-y-5">
                  <div className="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 space-y-2">
                    <div className="flex items-center gap-2 text-xs font-bold text-amber-900 dark:text-amber-200">
                      <ShieldAlert className="w-4 h-4 text-amber-600" />
                      <span>Pre-Switch Safety Audit Checklist</span>
                    </div>
                    <p className="text-xs text-amber-900/80 dark:text-amber-300/80">
                      Changing global operating mode restructures how orders reserve goods and how platforms broadcast stock. 
                      Review all safety criteria before proceeding.
                    </p>
                  </div>

                  {/* Checklist Items */}
                  <div className="space-y-3 text-xs">
                    <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
                      <div className="flex items-center gap-2.5">
                        <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white">Active Reserved Orders Audit</div>
                          <div className="text-neutral-500 text-[11px]">All open orders maintain valid lock reservations.</div>
                        </div>
                      </div>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300">Clear</span>
                    </div>

                    <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
                      <div className="flex items-center gap-2.5">
                        <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white">Inter-store In-progress Transfers</div>
                          <div className="text-neutral-500 text-[11px]">No active transfer locks in pipeline.</div>
                        </div>
                      </div>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300">Clear</span>
                    </div>

                    <div className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
                      <div className="flex items-center gap-2.5">
                        <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white">Platform Sync Queues</div>
                          <div className="text-neutral-500 text-[11px]">All platform webhooks and sync events reconciled.</div>
                        </div>
                      </div>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300">Clear</span>
                    </div>

                    {/* Uncounted SKUs Check */}
                    <div className={`p-3 rounded-xl border flex items-center justify-between ${
                      safetyBlockers.length > 0 
                        ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-300 dark:border-amber-800'
                        : 'bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700'
                    }`}>
                      <div className="flex items-center gap-2.5">
                        {safetyBlockers.length > 0 ? (
                          <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                        ) : (
                          <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                        )}
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white">
                            Verified Physical Count Readiness
                          </div>
                          <div className="text-neutral-500 text-[11px]">
                            {safetyBlockers.length > 0
                              ? `${safetyBlockers.length} SKU(s) are uncounted. Count must be verified before mode switch.`
                              : 'All catalog items have verified physical counts.'}
                          </div>
                        </div>
                      </div>
                      <span className={`text-[10px] font-bold px-2 py-0.5 rounded ${
                        safetyBlockers.length > 0 ? 'bg-amber-200 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200' : 'bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300'
                      }`}>
                        {safetyBlockers.length > 0 ? `${safetyBlockers.length} Notice` : 'Clear'}
                      </span>
                    </div>
                  </div>

                  {/* Blockers Resolution List */}
                  {safetyBlockers.length > 0 && (
                    <div className="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 space-y-2">
                      <div className="text-xs font-bold text-amber-950 dark:text-amber-200">
                        Items Requiring Physical Verification:
                      </div>
                      <div className="space-y-2">
                        {safetyBlockers.map(b => (
                          <div key={b.sku} className="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-neutral-800 border border-amber-200/80 dark:border-amber-700/60 text-xs">
                            <div>
                              <span className="font-mono font-bold text-neutral-900 dark:text-white">{b.sku}</span>
                              <span className="text-neutral-600 dark:text-neutral-400 ml-2">{b.productName}</span>
                            </div>
                            <button
                              type="button"
                              onClick={() => {
                                onClose();
                                if (onSelectSkuForCount) onSelectSkuForCount(b.sku);
                              }}
                              className="px-2.5 py-1 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 font-bold text-[11px] hover:bg-neutral-800 dark:hover:bg-neutral-100 cursor-pointer"
                            >
                              Go Count SKU
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Footer Buttons */}
                  <div className="pt-2 flex justify-between border-t border-neutral-100 dark:border-neutral-800">
                    <button
                      type="button"
                      onClick={() => setSwitchFlowStep('view')}
                      className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                    >
                      Back
                    </button>
                    <button
                      type="button"
                      onClick={handleProceedFromChecklist}
                      className="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 text-xs font-bold flex items-center gap-1.5 cursor-pointer"
                    >
                      <span>Proceed to {targetMode === 'independent' ? 'Store Allocation' : 'Confirmation'}</span>
                      <ArrowRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              )}

              {/* SWITCH STEP 2: ALLOCATION REVIEW (Shared -> Independent) */}
              {switchFlowStep === 'allocation_review' && (
                <div className="space-y-4">
                  <div className="p-3.5 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 space-y-1">
                    <div className="text-xs font-bold text-neutral-900 dark:text-white">
                      Step 2: Allocate Shared Stock Across Store Accounts
                    </div>
                    <p className="text-xs text-neutral-600 dark:text-neutral-300">
                      Distribute the current shared pool of each SKU into isolated store accounts. 
                      Strict conservation applies: the sum of units assigned to stores must equal the shared total.
                    </p>
                  </div>

                  <div className="space-y-3 max-h-64 overflow-y-auto pr-1">
                    {safeInventory.map(item => {
                      const total = item.availableStock ?? item.totalPhysicalStock ?? 0;
                      const activeStores = safeStores.filter(s => !s.isComingSoon && s.connectionState === 'connected');
                      const currentAlloc = allocations[item.masterSku] || {};
                      const sumAlloc = (Object.values(currentAlloc) as number[]).reduce((a: number, b: number) => a + b, 0);
                      const isBalanced = sumAlloc === total;

                      return (
                        <div key={item.id} className="p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 text-xs space-y-2">
                          <div className="flex items-center justify-between">
                            <div>
                              <span className="font-mono font-bold text-neutral-900 dark:text-white">{item.masterSku}</span>
                              <span className="text-neutral-600 dark:text-neutral-400 ml-2">{item.productName}</span>
                            </div>
                            <div className="font-mono text-xs">
                              Shared Total: <strong>{total}</strong> units
                            </div>
                          </div>

                          {/* Allocation Inputs */}
                          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1">
                            {activeStores.map(st => (
                              <div key={st.id} className="space-y-1">
                                <label className="text-[10px] text-neutral-500 truncate block">
                                  {st.nickname || st.name}
                                </label>
                                <input
                                  type="number"
                                  min="0"
                                  value={currentAlloc[st.id] ?? 0}
                                  onChange={e => {
                                    const val = Math.max(0, parseInt(e.target.value) || 0);
                                    setAllocations(prev => ({
                                      ...prev,
                                      [item.masterSku]: {
                                        ...(prev[item.masterSku] || {}),
                                        [st.id]: val
                                      }
                                    }));
                                  }}
                                  className="w-full px-2 py-1 text-xs font-mono font-bold rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                                />
                              </div>
                            ))}
                          </div>

                          <div className="flex justify-between items-center text-[11px] pt-1">
                            <span className={isBalanced ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-amber-600 dark:text-amber-400 font-bold'}>
                              {isBalanced ? '✓ Balanced' : `Mismatch: Allocated ${sumAlloc} / ${total}`}
                            </span>
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {/* Footer Buttons */}
                  <div className="pt-2 flex justify-between border-t border-neutral-100 dark:border-neutral-800">
                    <button
                      type="button"
                      onClick={() => setSwitchFlowStep('checklist')}
                      className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                    >
                      Back
                    </button>
                    <button
                      type="button"
                      onClick={() => setSwitchFlowStep('confirm')}
                      className="px-5 py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 text-xs font-bold flex items-center gap-1.5 cursor-pointer"
                    >
                      <span>Proceed to Admin Sign-off</span>
                      <ArrowRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              )}

              {/* SWITCH STEP 3: ADMIN CONFIRMATION */}
              {switchFlowStep === 'confirm' && (
                <div className="space-y-5">
                  <div className="p-4 rounded-xl bg-neutral-100 dark:bg-neutral-800/80 border border-neutral-200 dark:border-neutral-700 space-y-2">
                    <h4 className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
                      Confirm Global Operating Mode Transition
                    </h4>
                    <p className="text-xs text-neutral-600 dark:text-neutral-300">
                      You are switching the entire JW Ecommerce platform from <strong>{currentMode === 'shared' ? 'Shared Stock' : 'Independent Stock'}</strong> to <strong>{targetMode === 'shared' ? 'Shared Stock' : 'Independent Stock'}</strong>. 
                      This transition will be recorded in the system audit trail.
                    </p>
                  </div>

                  <div className="space-y-3">
                    <div className="space-y-1">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                        Admin Signature / Operator Name *
                      </label>
                      <input
                        type="text"
                        value={adminName}
                        onChange={e => setAdminName(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white font-medium"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                        Operational Reason *
                      </label>
                      <input
                        type="text"
                        value={switchReason}
                        onChange={e => setSwitchReason(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white"
                      />
                    </div>
                  </div>

                  <div className="pt-2 flex justify-between border-t border-neutral-100 dark:border-neutral-800">
                    <button
                      type="button"
                      onClick={() => setSwitchFlowStep('checklist')}
                      className="px-4 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer"
                    >
                      Back
                    </button>
                    <button
                      type="button"
                      disabled={isExecutingSwitch}
                      onClick={handleExecuteModeSwitch}
                      className="px-6 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 dark:bg-white dark:hover:bg-neutral-100 text-white dark:text-neutral-900 text-xs font-bold transition shadow-xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                      {isExecutingSwitch ? <RefreshCw className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                      <span>Execute Global Mode Transition</span>
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* TAB 2: STORE INVENTORY MANAGEMENT (TAKEOVER) */}
          {activeTab === 'stores' && (
            <div className="space-y-6">
              {/* Educational Warning on Unmanaged Stores */}
              <div className="p-4 rounded-2xl bg-amber-500/5 dark:bg-amber-500/10 border border-amber-200/80 dark:border-amber-800/50 flex items-start gap-3.5">
                <ShieldAlert className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                <div className="text-xs space-y-1 text-amber-950 dark:text-amber-200">
                  <div className="font-bold">
                    Crucial Policy: Unmanaged Stores & Real Physical Inventory
                  </div>
                  <p className="leading-relaxed opacity-90">
                    Stores marked as <strong>Not Managed by JW</strong> are not subject to automated JW inventory broadcast. However, <strong>sales on unmanaged stores still consume physical stock</strong> from your stockroom shelves. Omnichannel inventory consistency cannot be claimed until all active stores are taken over by JW.
                  </p>
                </div>
              </div>

              {/* Stores Grouped by Platform */}
              <div className="space-y-4">
                {/* 1. JW Ecom Storefront */}
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                    Direct Webstore Channel
                  </h4>
                  {safeStores.filter(s => s.platform === 'webstore').map(store => (
                    <div key={store.id} className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <MarketplaceLogo platform="webstore" size="sm" />
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white text-xs">{store.name}</div>
                          <div className="text-[11px] text-neutral-500">{store.nickname} • Direct Native Integration</div>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                          Managed by JW
                        </span>
                      </div>
                    </div>
                  ))}
                </div>

                {/* 2. Shopee */}
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                    Shopee Stores ({safeStores.filter(s => s.platform === 'shopee').length})
                  </h4>
                  {safeStores.filter(s => s.platform === 'shopee').map(store => (
                    <div key={store.id} className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <MarketplaceLogo platform="shopee" size="sm" />
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white text-xs">{store.name}</div>
                          <div className="text-[11px] text-neutral-500">
                            {store.nickname} • Connection:{' '}
                            <span className={store.connectionState === 'connected' ? 'text-emerald-600 font-semibold' : 'text-red-500 font-semibold'}>
                              {store.connectionState}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        {store.isManagedByJw ? (
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                            Managed by JW
                          </span>
                        ) : (
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300">
                            Not Managed by JW
                          </span>
                        )}
                        <button
                          type="button"
                          onClick={() => onToggleStoreManagement(store.id, !store.isManagedByJw)}
                          className="px-3 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer text-neutral-700 dark:text-neutral-300"
                        >
                          {store.isManagedByJw ? 'Pause Takeover' : 'Takeover Inventory'}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                {/* 3. Lazada */}
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                    Lazada Stores ({safeStores.filter(s => s.platform === 'lazada').length})
                  </h4>
                  {safeStores.filter(s => s.platform === 'lazada').map(store => (
                    <div key={store.id} className="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <MarketplaceLogo platform="lazada" size="sm" />
                        <div>
                          <div className="font-bold text-neutral-900 dark:text-white text-xs">{store.name}</div>
                          <div className="text-[11px] text-neutral-500">
                            {store.nickname} • Connection:{' '}
                            <span className={store.connectionState === 'connected' ? 'text-emerald-600 font-semibold' : 'text-red-500 font-semibold'}>
                              {store.connectionState}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        {store.isManagedByJw ? (
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                            Managed by JW
                          </span>
                        ) : (
                          <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300">
                            Not Managed by JW
                          </span>
                        )}
                        <button
                          type="button"
                          onClick={() => onToggleStoreManagement(store.id, !store.isManagedByJw)}
                          className="px-3 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs font-semibold hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer text-neutral-700 dark:text-neutral-300"
                        >
                          {store.isManagedByJw ? 'Pause Takeover' : 'Takeover Inventory'}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                {/* 4. TikTok & Shopify (Coming Soon) */}
                <div className="space-y-2">
                  <h4 className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                    Future Integrations (Coming Soon)
                  </h4>
                  {safeStores.filter(s => s.isComingSoon).map(store => (
                    <div key={store.id} className="p-4 rounded-xl bg-neutral-50/50 dark:bg-neutral-800/30 border border-dashed border-neutral-200 dark:border-neutral-800 flex items-center justify-between opacity-70">
                      <div className="flex items-center gap-3">
                        <MarketplaceLogo platform={store.platform as any} size="sm" />
                        <div>
                          <div className="font-bold text-neutral-700 dark:text-neutral-300 text-xs">{store.platformDisplayName}</div>
                          <div className="text-[11px] text-neutral-400">Scheduled for Phase 2 Rollout</div>
                        </div>
                      </div>
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500">
                        Coming Soon
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
