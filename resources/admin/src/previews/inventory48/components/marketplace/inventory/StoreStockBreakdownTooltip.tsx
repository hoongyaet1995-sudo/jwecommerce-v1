import React, { useId } from 'react';
import { Info } from 'lucide-react';
import { StoreStockAccountBalance } from './inventoryTypes';

export function StoreStockBreakdownTooltip({ balances }: { balances: StoreStockAccountBalance[] }) {
  const tooltipId = useId();

  if (balances.length === 0) return null;

  return (
    <span className="group relative inline-flex items-center">
      <button
        type="button"
        aria-label="View stock allocation by store"
        aria-describedby={tooltipId}
        className="inline-flex h-4 w-4 items-center justify-center rounded-full text-neutral-400 transition-colors hover:text-neutral-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:text-neutral-200"
      >
        <Info className="h-3.5 w-3.5" aria-hidden="true" />
      </button>
      <span
        id={tooltipId}
        role="tooltip"
        className="invisible absolute left-1/2 top-full z-40 mt-2 w-56 -translate-x-1/2 rounded-xl border border-neutral-200 bg-white p-3 opacity-0 shadow-xl transition-opacity group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 dark:border-neutral-700 dark:bg-neutral-900"
      >
        <span className="mb-2 block text-[10px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
          Stock by store
        </span>
        <span className="block space-y-1.5">
          {balances.map(balance => (
            <span key={balance.storeId} className="flex items-center justify-between gap-3 text-[11px] font-normal text-neutral-600 dark:text-neutral-300">
              <span className="min-w-0 truncate">{balance.storeNickname || balance.storeName}</span>
              <span className="shrink-0 font-mono font-semibold text-neutral-900 dark:text-white">
                {balance.physicalStock === null ? 'Not counted' : `${balance.physicalStock} units`}
              </span>
            </span>
          ))}
        </span>
      </span>
    </span>
  );
}
