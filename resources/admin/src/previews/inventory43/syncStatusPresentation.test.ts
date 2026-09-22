import { describe, expect, it } from 'vitest';
import { syncStatusPresentation } from './syncStatusPresentation';

describe('syncStatusPresentation', () => {
  it.each([
    ['confirmed', 'Synced', 'none'],
    ['auth_expired', 'Authorization Expired', 'reconnect'],
    ['failed', 'Sync Failed', 'retry'],
    ['uncertain', 'Awaiting Confirmation', 'check_status'],
    ['processing', 'Awaiting Confirmation', 'check_status'],
    ['queued', 'Awaiting Confirmation', 'check_status'],
    ['waiting_source', 'Waiting for Source', 'none'],
    ['superseded', 'Replaced by Newer Stock', 'none'],
    ['sku_not_mapped', 'SKU Not Mapped', 'mapping'],
  ] as const)('maps %s to the shared label and recovery action', (status, label, action) => {
    expect(syncStatusPresentation(status)).toMatchObject({ label, action });
  });
});
