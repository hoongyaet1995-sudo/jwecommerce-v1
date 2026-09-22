const providerStatus = (stages: any[] = []) => {
  const priority: Record<string, number> = { uncertain: 8, auth_expired: 7, failed: 6, processing: 5, queued: 4, waiting_source: 3, confirmed: 2, superseded: 1, not_applicable: 0 };
  return [...stages].sort((a, b) => (priority[b.status] || 0) - (priority[a.status] || 0))[0]?.status;
};

export function adaptInventoryHistoryEntry(h: any) {
  const activity = h.result?.activity || {};
  const transfer = h.result?.transfer;
  const source = activity.source_store;
  const destination = activity.destination_store;
  return {
    id: h.id, timestamp: h.created_at,
    actionType: h.kind === 'count' || h.kind === 'allocation' ? 'first_count' : h.kind === 'intake' ? 'actual_intake' : h.kind === 'correction' ? 'stock_adjustment' : h.kind === 'transfer' ? 'store_transfer' : h.kind,
    masterSku: activity.master_sku || transfer?.master_sku || 'Unavailable',
    productName: activity.product_name || transfer?.product_name || 'Unavailable',
    variantName: activity.variant_name || transfer?.variant_name,
    targetScope: source && destination ? `${source.name} → ${destination.name}` : activity.store?.name || 'JW inventory',
    storeId: activity.store?.id, platform: activity.store?.platform,
    quantityChange: Number(activity.quantity_change ?? transfer?.quantity ?? 0),
    previousStock: activity.previous_stock ?? 'Unavailable', resultingStock: activity.resulting_stock ?? 'Unavailable',
    operator: h.actor, reason: activity.reason || transfer?.reason || h.kind, notes: activity.notes || undefined,
    referenceId: h.id, status: h.ledger_status === 'applied' ? 'succeeded' : 'failed',
    providerSyncStatus: providerStatus(h.result?.external_stages), detailsPayload: h.result || {},
  };
}

const restore = (entries: any[]) => entries.filter(entry => entry.actionType === 'store_transfer').map(entry => {
  const receipt = entry.detailsPayload || {};
  const stages = receipt.external_stages || [];
  const status = (stage: string) => {
    const value = stages.find((item: any) => item.stage === stage)?.status;
    return value === 'confirmed' || value === 'superseded' || value === 'not_applicable' ? 'succeeded'
      : value === 'uncertain' ? 'awaiting_confirmation'
      : value === 'failed' || value === 'auth_expired' ? 'failed'
      : value === 'processing' ? 'syncing' : 'pending';
  };
  const reason = (stage: string) => stages.find((item: any) => item.stage === stage)?.provider_receipt?.message;
  const transfer = receipt.transfer || {};
  return {
    id: entry.id, transferNumber: entry.referenceId || entry.id, timestamp: entry.timestamp,
    masterSku: entry.masterSku, productName: entry.productName, variantName: entry.variantName,
    sourceStoreId: transfer.source_store?.id || '', sourceStoreName: transfer.source_store?.name || 'Unavailable', sourcePlatform: transfer.source_store?.platform || 'webstore',
    destinationStoreId: transfer.destination_store?.id || '', destinationStoreName: transfer.destination_store?.name || 'Unavailable', destinationPlatform: transfer.destination_store?.platform || 'webstore',
    quantity: Number(transfer.quantity ?? entry.quantityChange ?? 0), operator: entry.operator,
    reason: transfer.reason || entry.reason, ledgerStatus: 'succeeded',
    sourcePlatformSyncStatus: status('source'), destinationPlatformSyncStatus: status('destination'),
    sourceFailureReason: reason('source'), destinationFailureReason: reason('destination'), externalStages: stages,
  };
});

export const restoreTransferHistory = Object.assign(restore, {
  merge(current: any[], refreshed: any[]) {
    const refreshedIds = new Set(refreshed.map(item => item.id));
    return [...refreshed, ...current.filter(item => !refreshedIds.has(item.id))];
  },
});
