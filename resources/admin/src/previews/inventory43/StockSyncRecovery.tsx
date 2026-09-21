import React, { useEffect, useState } from 'react';
import { storeInventoryApi } from '../../integration/storeInventory';
import { StockSyncDetailsModal } from './StockSyncDetailsModal';
import type { StoreSyncItemState } from './stockSyncTypes';

/** Data adapter only; the modal presentation is extracted from ZIP 43. */
export function StockSyncRecovery({item, store, balance, onClose, onChanged, onObserved, onError, onNavigate}: any) {
  const [info, setInfo] = useState<StoreSyncItemState | null>(null);
  const internal = store.platform === 'webstore';
  const refresh = () => storeInventoryApi.syncDetails(store.id, Number(item.variantId));
  useEffect(() => {
    let active = true;
    if (internal) {
      setInfo({sku:item.masterSku,storeId:store.id,status:'jw_internal',
        jwAvailableStock:balance?.availableStock ?? item.availableStock ?? null,
        marketplaceReportedStock:null,difference:null,lastCheckedAt:null,lastConfirmedSyncAt:null,
        canCheck:false,canResync:false});
    } else {
      refresh().then(result => {if (active) {setInfo(result);onObserved?.(result);}}).catch(error => {
        if (active) {onError(error.message); onClose();}
      });
    }
    return () => {active = false;};
  }, [store.id, item.variantId]);
  return <StockSyncDetailsModal isOpen onClose={onClose}
    item={internal ? {...item,totalPhysicalStock:balance?.physicalStock ?? item.totalPhysicalStock,reservedStock:balance?.reservedStock ?? item.reservedStock} : item}
    store={store} syncInfo={info}
    onRefresh={refresh}
    onCheck={() => storeInventoryApi.checkStock(store.id,Number(item.variantId))}
    onResync={key => storeInventoryApi.resyncStock(store.id,Number(item.variantId),key)}
    onUpdateSyncInfo={result => {onObserved?.(result);void onChanged();}}
    onNavigateToSkuMapping={() => {onClose();onNavigate('marketplace-mapping');}}
    onNavigateToStoreSettings={() => {onClose();onNavigate('marketplace-auth');}}
    onNavigateToHistory={() => {onClose();onNavigate('history');}}
  />;
}
