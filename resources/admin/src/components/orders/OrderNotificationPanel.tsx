import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Clock, ExternalLink, Eye, History, Mail } from 'lucide-react';
import { useAdmin } from '../../context/AdminContext';
import {
  fetchOrderNotificationDispatches,
  previewOrderNotification,
  resendNotificationDispatch,
  retryNotificationDispatch,
  sendCurrentOrderNotification,
} from '../../integration/orderNotificationData';
import type {
  NotificationDispatch,
  NotificationDispatchState,
  NotificationEventKey,
  Order,
  OrderNotificationPreview,
  OrderNotificationSummary,
} from '../../types';

interface OrderNotificationPanelProps {
  order: Order;
  eventKey: NotificationEventKey | null;
  staffNote?: string;
  notifyCustomer?: boolean;
  mutationDispatch?: OrderNotificationSummary | null;
  auditRefreshKey?: number;
  onNotifyCustomerChange?: (checked: boolean) => void;
  onConfigureStatuses?: () => void;
  onManageTemplates?: () => void;
  frameClassName?: string;
  frameTitle?: string;
  children?: React.ReactNode;
}

const DEFAULT_FRAME_CLASS = 'p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/60 space-y-3';

const stateLabel = (state: NotificationDispatchState): string => ({
  queued: 'Queued',
  sending: 'Sending',
  sent: 'Sent',
  failed: 'Failed',
})[state];

const stateClassName = (state: NotificationDispatchState): string => ({
  queued: 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800',
  sending: 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-800',
  sent: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
  failed: 'bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800',
})[state];

const sourceLabel = (source: NotificationDispatch['source']): string => ({
  automatic: 'Automatic',
  admin_current_template: 'Current template',
  manual_resend: 'Manual resend',
  test: 'Test',
})[source];

const safeFailureLabel = (code: string | null): string => {
  if (code === 'timeout') return 'Delivery timed out';
  if (code === 'transport') return 'Email transport unavailable';
  return 'Delivery failed';
};

const timestamp = (value: string | null): string | null => {
  if (!value) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed.toLocaleString();
};

export const OrderNotificationPanel: React.FC<OrderNotificationPanelProps> = ({
  order,
  eventKey,
  staffNote = '',
  notifyCustomer = true,
  mutationDispatch = null,
  auditRefreshKey = 0,
  onNotifyCustomerChange,
  onConfigureStatuses,
  onManageTemplates,
  frameClassName = DEFAULT_FRAME_CLASS,
  frameTitle = 'Update Order Status & Dispatch Customer Notification',
  children,
}) => {
  const { laravelBootstrap, notificationPermissions, setCurrentSection } = useAdmin();
  const [showPreview, setShowPreview] = useState(false);
  const [preview, setPreview] = useState<OrderNotificationPreview | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState('');
  const [dispatches, setDispatches] = useState<NotificationDispatch[]>([]);
  const [auditLoading, setAuditLoading] = useState(false);
  const [auditError, setAuditError] = useState('');
  const [actionId, setActionId] = useState<string | null>(null);
  const [actionError, setActionError] = useState('');
  const [actionState, setActionState] = useState<NotificationDispatchState | null>(null);
  const currentOrderIdRef = useRef(order.id);
  const previewRequestRef = useRef(0);
  const auditRequestRef = useRef(0);
  const actionRequestRef = useRef(0);
  const previewFingerprint = order.notificationContextFingerprint;
  const currentPreviewFingerprintRef = useRef(previewFingerprint);

  currentOrderIdRef.current = order.id;
  currentPreviewFingerprintRef.current = previewFingerprint;

  const canView = notificationPermissions.includes('notifications.deliveries.view');
  const canRetry = notificationPermissions.includes('notifications.deliveries.retry');
  const canResend = notificationPermissions.includes('notifications.deliveries.resend');
  const canSendCurrent = notificationPermissions.includes('notifications.current.send');
  const canPreview = canView || canSendCurrent;

  const loadDispatches = useCallback(async (orderId: string): Promise<void> => {
    const requestId = ++auditRequestRef.current;
    setAuditLoading(true);
    setAuditError('');

    try {
      const loaded = await fetchOrderNotificationDispatches(laravelBootstrap, orderId);
      if (requestId !== auditRequestRef.current || currentOrderIdRef.current !== orderId) return;
      setDispatches(loaded);
    } catch (error) {
      if (requestId !== auditRequestRef.current || currentOrderIdRef.current !== orderId) return;
      setDispatches([]);
      setAuditError(error instanceof Error ? error.message : 'Order notification history could not be loaded.');
    } finally {
      if (requestId === auditRequestRef.current && currentOrderIdRef.current === orderId) {
        setAuditLoading(false);
      }
    }
  }, [laravelBootstrap]);

  useEffect(() => {
    setShowPreview(false);
  }, [order.id]);

  useEffect(() => {
    actionRequestRef.current += 1;
    setActionId(null);
    setActionError('');
    setActionState(null);
  }, [eventKey, order.id, previewFingerprint, staffNote]);

  useEffect(() => {
    if (!canView) {
      auditRequestRef.current += 1;
      setDispatches([]);
      setAuditLoading(false);
      setAuditError('You do not have permission to view Email delivery audit.');
      return;
    }

    void loadDispatches(order.id);
    return () => {
      auditRequestRef.current += 1;
    };
  }, [
    auditRefreshKey,
    canView,
    loadDispatches,
    order.id,
    order.notificationSummary?.publicId,
    order.notificationSummary?.state,
  ]);

  useEffect(() => {
    const requestId = ++previewRequestRef.current;
    setPreview(null);
    setPreviewError('');

    if (!eventKey || !canPreview) {
      setPreviewLoading(false);
      if (eventKey && !canPreview) {
        setPreviewError('You do not have permission to preview Email notifications.');
      }
      return;
    }

    const orderId = order.id;
    const orderFingerprint = previewFingerprint;
    setPreviewLoading(true);
    void previewOrderNotification(laravelBootstrap, orderId, {
      eventKey,
      staffNote: staffNote || null,
    }).then((loaded) => {
      if (requestId !== previewRequestRef.current || currentOrderIdRef.current !== orderId || currentPreviewFingerprintRef.current !== orderFingerprint) return;
      setPreview(loaded);
    }).catch((error: unknown) => {
      if (requestId !== previewRequestRef.current || currentOrderIdRef.current !== orderId || currentPreviewFingerprintRef.current !== orderFingerprint) return;
      setPreviewError(error instanceof Error ? error.message : 'Order notification preview could not be loaded.');
    }).finally(() => {
      if (requestId === previewRequestRef.current && currentOrderIdRef.current === orderId && currentPreviewFingerprintRef.current === orderFingerprint) {
        setPreviewLoading(false);
      }
    });
  }, [canPreview, eventKey, laravelBootstrap, order.id, previewFingerprint, staffNote]);

  const runAction = async (
    id: string,
    action: () => Promise<NotificationDispatch>,
  ): Promise<void> => {
    const requestId = ++actionRequestRef.current;
    const orderId = order.id;
    const orderFingerprint = previewFingerprint;
    setActionId(id);
    setActionError('');
    setActionState(null);

    try {
      const result = await action();
      if (requestId !== actionRequestRef.current || currentOrderIdRef.current !== orderId || currentPreviewFingerprintRef.current !== orderFingerprint) return;
      setActionState(result.state);
      if (canView) await loadDispatches(orderId);
    } catch (error) {
      if (requestId !== actionRequestRef.current || currentOrderIdRef.current !== orderId || currentPreviewFingerprintRef.current !== orderFingerprint) return;
      setActionError(error instanceof Error ? error.message : 'Notification action could not be completed.');
    } finally {
      if (requestId === actionRequestRef.current && currentOrderIdRef.current === orderId && currentPreviewFingerprintRef.current === orderFingerprint) {
        setActionId(null);
      }
    }
  };

  const handleRetry = (dispatch: NotificationDispatch): void => {
    void runAction(`retry:${dispatch.id}`, () => retryNotificationDispatch(laravelBootstrap, dispatch.id));
  };

  const handleResend = (dispatch: NotificationDispatch): void => {
    if (!window.confirm('Resend this Email using its original frozen content?')) return;
    void runAction(`resend:${dispatch.id}`, () => resendNotificationDispatch(laravelBootstrap, dispatch.id));
  };

  const handleSendCurrent = (): void => {
    if (!eventKey || !window.confirm('Send the current Email template and preview to this customer?')) return;
    void runAction('send-current', () => sendCurrentOrderNotification(laravelBootstrap, order.id, {
      eventKey,
      staffNote: staffNote || null,
    }));
  };

  const configureStatuses = (): void => {
    if (onConfigureStatuses) onConfigureStatuses();
    else setCurrentSection('settings-orders-status');
  };

  const manageTemplates = (): void => {
    if (onManageTemplates) onManageTemplates();
    else setCurrentSection('settings-email');
  };

  return (
    <>
      {/* osCommerce Order Status & Customer Notification Control Hub */}
      <div className={frameClassName}>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
            <span className="text-xs font-bold text-neutral-900 dark:text-white uppercase tracking-wider">
              {frameTitle}
            </span>
          </div>
          <button
            type="button"
            onClick={configureStatuses}
            className="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold hover:underline"
          >
            Configure Statuses →
          </button>
        </div>

        {children}

        <div className="space-y-2 pt-1">
          <div className="flex items-center justify-between flex-wrap gap-2 text-xs">
            <label className="flex items-center gap-1.5 cursor-pointer text-neutral-700 dark:text-neutral-300 font-semibold">
              <input
                type="checkbox"
                checked={Boolean(eventKey) && notifyCustomer}
                disabled={!eventKey}
                onChange={event => onNotifyCustomerChange?.(event.target.checked)}
                className="w-4 h-4 rounded text-indigo-600 accent-indigo-600 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <span>Dispatch Email Update to {order.customerEmail}</span>
            </label>

            <button
              type="button"
              disabled={!canPreview || !eventKey}
              onClick={() => setShowPreview(value => !value)}
              className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Eye className="w-3 h-3" />
              <span>{showPreview ? 'Hide Template Preview' : 'Preview Message Template'}</span>
            </button>
          </div>

          {canView && mutationDispatch ? (
            <div className="p-2.5 rounded-xl bg-white/80 dark:bg-neutral-800/80 border border-indigo-200 dark:border-indigo-900/60 text-[11px] font-semibold text-neutral-700 dark:text-neutral-300">
              Status update Email intent: <span className="text-indigo-600 dark:text-indigo-400">{stateLabel(mutationDispatch.state)}</span>
            </div>
          ) : null}

          {showPreview ? (
            <div className="p-3 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200/70 dark:border-indigo-800/60 text-xs space-y-2">
              <div className="flex items-center justify-between gap-3">
                <span className="font-bold text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                  <Mail className="w-3.5 h-3.5" />
                  Template Trigger: {preview?.statusName || 'Status Update'}
                </span>
                <button
                  type="button"
                  onClick={manageTemplates}
                  className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 cursor-pointer"
                >
                  <span>Manage Templates in Settings</span>
                  <ExternalLink className="w-2.5 h-2.5" />
                </button>
              </div>

              {previewLoading ? (
                <p className="text-[11px] text-neutral-500 dark:text-neutral-400">Loading current Email preview...</p>
              ) : null}
              {previewError ? (
                <p className="text-[11px] text-rose-600 dark:text-rose-400 font-medium">{previewError}</p>
              ) : null}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-[11px]">
                <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-indigo-100 dark:border-indigo-900/50 space-y-1">
                  <div className="flex items-center justify-between font-bold text-neutral-800 dark:text-neutral-200">
                    <span>✉️ Email Subject:</span>
                    <span className={preview?.emailEnabled ? 'text-emerald-600' : 'text-neutral-400'}>
                      {preview ? (preview.emailEnabled ? 'Enabled' : 'DORMANT') : 'Unavailable'}
                    </span>
                  </div>
                  <p className="text-neutral-600 dark:text-neutral-300 font-mono text-[10px] break-words">
                    {preview?.subject || 'Current Email preview unavailable'}
                  </p>
                  {preview ? (
                    <p className="text-neutral-600 dark:text-neutral-300 text-[10px] whitespace-pre-line">
                      {preview.body}
                    </p>
                  ) : null}
                </div>

                <div className="p-2.5 rounded-lg bg-white dark:bg-neutral-900 border border-indigo-100 dark:border-indigo-900/50 space-y-1">
                  <div className="flex items-center justify-between font-bold text-neutral-800 dark:text-neutral-200">
                    <span>💬 WhatsApp Message:</span>
                    <span className="text-neutral-400">Not connected</span>
                  </div>
                  <p className="text-neutral-600 dark:text-neutral-300 font-mono text-[10px] line-clamp-2">
                    WhatsApp delivery is not connected. No message will be sent.
                  </p>
                </div>
              </div>
            </div>
          ) : null}

          <div className="flex items-center justify-end gap-2">
            <button
              type="button"
              disabled={!canSendCurrent || !eventKey || actionId !== null || previewLoading || preview === null || !preview.emailEnabled}
              onClick={handleSendCurrent}
              className="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-[11px] transition cursor-pointer"
            >
              {actionId === 'send-current' ? 'Queuing...' : 'Send Current Template'}
            </button>
          </div>

          {actionState ? (
            <p className="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold">Email action state: {stateLabel(actionState)}</p>
          ) : null}
          {actionError ? (
            <p className="text-[11px] text-rose-600 dark:text-rose-400 font-medium">{actionError}</p>
          ) : null}
        </div>
      </div>

      <div className="p-4 rounded-2xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/40 dark:bg-neutral-800/30 space-y-3">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <History className="w-4 h-4 text-indigo-500" />
            <h4 className="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
              Email Delivery Audit
            </h4>
          </div>
          <span className="text-[10px] text-neutral-400 font-mono">Server audit only</span>
        </div>

        {auditLoading ? (
          <p className="text-[11px] text-neutral-500 dark:text-neutral-400">Loading Email delivery audit...</p>
        ) : null}
        {auditError ? (
          <p className="text-[11px] text-rose-600 dark:text-rose-400 font-medium">{auditError}</p>
        ) : null}
        {!auditLoading && !auditError && dispatches.length === 0 ? (
          <p className="text-[11px] text-neutral-500 dark:text-neutral-400">No Email delivery audit is available for this order.</p>
        ) : null}

        {!auditLoading && dispatches.length > 0 ? (
          <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
            {dispatches.map(dispatch => (
              <div
                key={dispatch.id}
                className="p-2.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200/80 dark:border-neutral-800 text-xs space-y-2 shadow-2xs"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="font-bold text-neutral-900 dark:text-white break-words">{dispatch.subject}</div>
                    <div className="flex items-center gap-1.5 flex-wrap mt-1 text-[10px] text-neutral-500 dark:text-neutral-400">
                      <span>Email</span>
                      <span>•</span>
                      <span>{sourceLabel(dispatch.source)}</span>
                      <span>•</span>
                      <span>Attempt {dispatch.attemptCount}</span>
                    </div>
                  </div>
                  <span className={`inline-flex text-[10px] font-bold px-2 py-0.5 rounded-full border whitespace-nowrap ${stateClassName(dispatch.state)}`}>
                    {stateLabel(dispatch.state)}
                  </span>
                </div>

                <div className="flex items-center gap-x-3 gap-y-1 flex-wrap text-[10px] font-mono text-neutral-400">
                  {timestamp(dispatch.queuedAt) ? <span>Queued: {timestamp(dispatch.queuedAt)}</span> : null}
                  {timestamp(dispatch.sentAt) ? <span>Sent: {timestamp(dispatch.sentAt)}</span> : null}
                  {timestamp(dispatch.failedAt) ? <span>Failed: {timestamp(dispatch.failedAt)}</span> : null}
                </div>
                {dispatch.state === 'failed' ? (
                  <p className="text-[10px] text-rose-600 dark:text-rose-400 font-medium">{safeFailureLabel(dispatch.errorCode)}</p>
                ) : null}

                {dispatch.state === 'failed' || dispatch.state === 'sent' ? (
                  <div className="flex justify-end">
                    {dispatch.state === 'failed' ? (
                      <button
                        type="button"
                        disabled={!canRetry || actionId !== null}
                        onClick={() => handleRetry(dispatch)}
                        className="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 dark:hover:bg-rose-900/60 disabled:opacity-50 disabled:cursor-not-allowed text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/80 font-bold text-[11px] transition cursor-pointer"
                      >
                        {actionId === `retry:${dispatch.id}` ? 'Queuing...' : 'Retry'}
                      </button>
                    ) : null}
                    {dispatch.state === 'sent' ? (
                      <button
                        type="button"
                        disabled={!canResend || actionId !== null}
                        onClick={() => handleResend(dispatch)}
                        className="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 disabled:opacity-50 disabled:cursor-not-allowed text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800/80 font-bold text-[11px] transition cursor-pointer"
                      >
                        {actionId === `resend:${dispatch.id}` ? 'Queuing...' : 'Resend'}
                      </button>
                    ) : null}
                  </div>
                ) : null}
              </div>
            ))}
          </div>
        ) : null}
      </div>
    </>
  );
};
