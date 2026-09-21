import React from 'react';

type StockSyncStoreButtonProps = {
  storeName: string;
  onOpen?: () => void;
  children: React.ReactNode;
};

const baseClasses = 'w-full flex items-center justify-between p-1.5 rounded-lg bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200/60 dark:border-neutral-700/60 text-[11px] text-left';
const interactiveClasses = ' cursor-pointer transition-colors hover:border-violet-400 dark:hover:border-violet-500 hover:bg-violet-50/60 dark:hover:bg-violet-950/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/70';

export const StockSyncStoreButton: React.FC<StockSyncStoreButtonProps> = ({
  storeName,
  onOpen,
  children,
}) => {
  if (!onOpen) {
    return <div className={baseClasses}>{children}</div>;
  }

  return (
    <button
      type="button"
      aria-label={`View stock sync details for ${storeName}`}
      title="View stock sync details"
      onClick={onOpen}
      className={`${baseClasses}${interactiveClasses}`}
    >
      {children}
    </button>
  );
};
