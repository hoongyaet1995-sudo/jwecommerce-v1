import type { OperationalSyncStatus } from './stockSyncTypes';

export type SyncRecoveryAction = 'none' | 'check_status' | 'retry' | 'reconnect' | 'mapping';

export type SyncPresentation = {
  status: OperationalSyncStatus | 'waiting_for_source';
  label: string;
  action: SyncRecoveryAction;
  actionLabel?: string;
};

export function syncStatusPresentation(status?: string | null): SyncPresentation {
  switch (status) {
    case 'confirmed':
    case 'succeeded':
    case 'synced':
    case 'jw_internal':
      return { status: status === 'jw_internal' ? 'jw_internal' : 'synced', label: status === 'jw_internal' ? 'JW Direct Ledger' : 'Synced', action: 'none' };
    case 'not_applicable':
      return { status: 'not_synced', label: 'Sync Not Enabled', action: 'none' };
    case 'auth_expired':
      return { status: 'auth_expired', label: 'Authorization Expired', action: 'reconnect', actionLabel: 'Reconnect Store' };
    case 'failed':
    case 'sync_failed':
      return { status: 'sync_failed', label: 'Sync Failed', action: 'retry', actionLabel: 'Retry Update' };
    case 'uncertain':
    case 'processing':
    case 'queued':
    case 'pending':
    case 'syncing':
    case 'awaiting_confirmation':
      return { status: 'awaiting_confirmation', label: 'Awaiting Confirmation', action: 'check_status', actionLabel: 'Check Status' };
    case 'waiting_source':
      return { status: 'waiting_for_source', label: 'Waiting for Source', action: 'none' };
    case 'superseded':
    case 'sync_replaced':
      return { status: 'sync_replaced', label: 'Replaced by Newer Stock', action: 'none' };
    case 'sku_not_mapped':
      return { status: 'sku_not_mapped', label: 'SKU Not Mapped', action: 'mapping', actionLabel: 'Go to SKU Mapping' };
    case 'listing_not_eligible':
      return { status: 'listing_not_eligible', label: 'Listing Not Eligible', action: 'none' };
    case 'not_synced':
    default:
      return { status: 'not_synced', label: 'Not Synced', action: 'retry', actionLabel: 'Resync Stock' };
  }
}
