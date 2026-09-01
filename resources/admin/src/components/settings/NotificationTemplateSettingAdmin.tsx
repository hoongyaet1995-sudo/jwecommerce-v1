import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useAdmin } from '../../context/AdminContext';
import { NotificationApiError } from '../../types';
import type {
  NotificationEventKey,
  NotificationPermission,
  NotificationTemplate,
  NotificationTemplateUpdate,
} from '../../types';
import {
  fetchNotificationTemplates,
  resetNotificationTemplates,
  saveNotificationTemplate,
  sendNotificationTest,
} from '../../integration/notificationTemplateData';
import {
  Mail,
  MessageSquare,
  Sparkles,
  Save,
  RotateCcw,
  Send,
  Eye,
  CheckCircle2,
  AlertCircle,
  Copy,
  Plus,
  Trash2,
  ExternalLink,
  Smartphone,
  Monitor,
  HelpCircle,
  Check,
  Tag,
  Clock,
  ChevronRight,
  RefreshCw,
  Info,
  ShieldCheck,
  Truck,
  ArrowRight
} from 'lucide-react';


interface NotificationVariable {
  key: string;
  label: string;
  example: string;
  category: 'order' | 'customer' | 'payment' | 'shipping' | 'store';
}

const NOTIFICATION_VARIABLES: NotificationVariable[] = [
  { key: '{order_number}', label: 'Order Number', example: 'ORD-9845', category: 'order' },
  { key: '{order_total}', label: 'Order Total', example: 'RM 439.00', category: 'order' },
  { key: '{order_date}', label: 'Order Date', example: 'Aug 30, 2026', category: 'order' },
  { key: '{order_items_summary}', label: 'Items Summary', example: '3 items (Aurora Pro Wireless Earbuds x 1...)', category: 'order' },
  { key: '{order_url}', label: 'Order Tracking Link', example: 'https://ecom.jwaistudio.online/order-tracking', category: 'order' },
  { key: '{status_name}', label: 'Status Name', example: 'Processing & Verified', category: 'order' },
  { key: '{staff_note}', label: 'Customer Note / Admin Comment', example: 'Package packed with fragile bubble wrap.', category: 'order' },
  { key: '{customer_name}', label: 'Customer Name', example: 'Elena Rostova', category: 'customer' },
  { key: '{customer_first_name}', label: 'Customer First Name', example: 'Elena', category: 'customer' },
  { key: '{customer_email}', label: 'Customer Email', example: 'elena.rostova@techlux.co', category: 'customer' },
  { key: '{customer_phone}', label: 'Customer Phone', example: '+6012-345 6789', category: 'customer' },
  { key: '{payment_method}', label: 'Payment Method', example: 'Bank In / DuitNow', category: 'payment' },
  { key: '{payment_reference}', label: 'Payment Reference', example: 'BANK-TRANSFER-9845', category: 'payment' },
  { key: '{payment_proof_reason}', label: 'Proof Rejection Reason', example: 'Transfer slip amount does not match order grand total.', category: 'payment' },
  { key: '{shipping_address}', label: 'Shipping Address', example: 'Level 18, Menara TechLux, KL', category: 'shipping' },
  { key: '{carrier_name}', label: 'Courier / Carrier Name', example: 'J&T Express Malaysia', category: 'shipping' },
  { key: '{tracking_number}', label: 'Tracking / AWB Number', example: 'JTE892410294MY', category: 'shipping' },
  { key: '{tracking_url}', label: 'JW Order Tracking URL', example: 'https://ecom.jwaistudio.online/order-tracking', category: 'shipping' },
  { key: '{shop_name}', label: 'Store Name', example: 'JW Studio', category: 'store' },
  { key: '{shop_email}', label: 'Store Email', example: 'support@jwstudio.my', category: 'store' },
  { key: '{shop_phone}', label: 'Store Phone', example: '+60 3-8888 1234', category: 'store' },
  { key: '{shop_whatsapp}', label: 'Store WhatsApp', example: 'https://wa.me/60123456789', category: 'store' },
];

const editableTemplateFields = (template: NotificationTemplate): NotificationTemplateUpdate => ({
  emailEnabled: template.emailEnabled,
  emailSubject: template.emailSubject,
  emailPreheader: template.emailPreheader,
  emailHeading: template.emailHeading,
  emailBody: template.emailBody,
  emailCtaText: template.emailCtaText,
  emailCtaUrl: template.emailCtaUrl,
  whatsappEnabled: template.whatsappEnabled,
  whatsappMessage: template.whatsappMessage,
  whatsappCtaButtons: template.whatsappCtaButtons,
  version: template.version,
});

export const NotificationTemplateSettingAdmin: React.FC = () => {
  const {
    laravelBootstrap,
    notificationPermissions,
    shopSettings,
  } = useAdmin();
  const permissionSet = new Set<NotificationPermission>(notificationPermissions);
  const canViewTemplates = permissionSet.has('notifications.templates.view');
  const canManageTemplates = permissionSet.has('notifications.templates.manage');
  const canSendTests = permissionSet.has('notifications.test.send');

  const [notificationTemplates, setNotificationTemplates] = useState<NotificationTemplate[]>([]);
  const [selectedStatusId, setSelectedStatusId] = useState<NotificationEventKey>('order_created');
  const [activeChannel, setActiveChannel] = useState<'email' | 'whatsapp'>('email');
  const [previewDevice, setPreviewDevice] = useState<'desktop' | 'mobile'>('desktop');
  const [selectedVarCategory, setSelectedVarCategory] = useState<string>('all');
  const [isTestModalOpen, setIsTestModalOpen] = useState(false);
  const [testRecipient, setTestRecipient] = useState('customer@example.com');
  const [testPhone, setTestPhone] = useState('+60 12-345 6789');
  const [localFormData, setLocalFormData] = useState<NotificationTemplate | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isResetting, setIsResetting] = useState(false);
  const [isTesting, setIsTesting] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);
  const [operationStatus, setOperationStatus] = useState<'Saved' | 'Reset' | 'Queued' | 'Not connected' | null>(null);

  const emailSubjectRef = useRef<HTMLInputElement>(null);
  const emailBodyRef = useRef<HTMLTextAreaElement>(null);
  const whatsappBodyRef = useRef<HTMLTextAreaElement>(null);
  const [lastFocusedField, setLastFocusedField] = useState<'subject' | 'emailBody' | 'whatsappBody'>('emailBody');

  useEffect(() => {
    let active = true;

    if (!canViewTemplates) {
      setIsLoading(false);
      return () => {
        active = false;
      };
    }

    setIsLoading(true);
    void fetchNotificationTemplates(laravelBootstrap)
      .then((templates) => {
        if (!active) return;
        setNotificationTemplates(templates);
        setSelectedStatusId((selected) => templates.some(template => template.eventKey === selected)
          ? selected
          : (templates[0]?.eventKey ?? 'order_created'));
        setLoadError(null);
      })
      .catch((error: unknown) => {
        if (!active) return;
        setLoadError(error instanceof Error ? error.message : 'Notification templates could not be loaded.');
      })
      .finally(() => {
        if (active) setIsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [canViewTemplates, laravelBootstrap]);

  const allTemplates = notificationTemplates;
  const currentTemplate = useMemo(
    () => allTemplates.find(template => template.eventKey === selectedStatusId) ?? allTemplates[0] ?? null,
    [allTemplates, selectedStatusId],
  );

  useEffect(() => {
    if (currentTemplate) {
      setLocalFormData({ ...currentTemplate });
    }
  }, [currentTemplate]);

  const filteredVariables = useMemo(() => {
    const allowed = new Set(currentTemplate?.variables ?? []);
    return NOTIFICATION_VARIABLES.filter(variable => (
      allowed.has(variable.key)
      && (selectedVarCategory === 'all' || variable.category === selectedVarCategory)
    ));
  }, [currentTemplate, selectedVarCategory]);

  if (!canViewTemplates) {
    return (
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs">
        <p className="text-sm font-semibold text-neutral-900 dark:text-white">Notification templates are not available for this account.</p>
      </div>
    );
  }

  if (isLoading || (!localFormData && !loadError)) {
    return (
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs">
        <p className="text-xs text-neutral-500">Loading notification templates...</p>
      </div>
    );
  }

  if (loadError || !localFormData) {
    return (
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-rose-200 dark:border-rose-900 p-6 shadow-xs">
        <p className="text-sm font-semibold text-rose-700 dark:text-rose-300">{loadError ?? 'Notification templates are unavailable.'}</p>
      </div>
    );
  }

  const handleInsertVariable = (variableKey: string) => {
    if (!canManageTemplates || !currentTemplate?.variables.includes(variableKey)) return;

    if (activeChannel === 'email') {
      if (lastFocusedField === 'subject') {
        const input = emailSubjectRef.current;
        if (input) {
          const start = input.selectionStart || 0;
          const end = input.selectionEnd || 0;
          const currentVal = localFormData.emailSubject || '';
          const newVal = currentVal.substring(0, start) + variableKey + currentVal.substring(end);
          setLocalFormData(prev => prev ? { ...prev, emailSubject: newVal } : null);
          setTimeout(() => {
            input.focus();
            input.setSelectionRange(start + variableKey.length, start + variableKey.length);
          }, 50);
        } else {
          setLocalFormData(prev => prev ? { ...prev, emailSubject: (prev.emailSubject || '') + ' ' + variableKey } : null);
        }
      } else {
        const textarea = emailBodyRef.current;
        if (textarea) {
          const start = textarea.selectionStart || 0;
          const end = textarea.selectionEnd || 0;
          const currentVal = localFormData.emailBody || '';
          const newVal = currentVal.substring(0, start) + variableKey + currentVal.substring(end);
          setLocalFormData(prev => prev ? { ...prev, emailBody: newVal } : null);
          setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(start + variableKey.length, start + variableKey.length);
          }, 50);
        } else {
          setLocalFormData(prev => prev ? { ...prev, emailBody: (prev.emailBody || '') + ' ' + variableKey } : null);
        }
      }
    } else {
      const textarea = whatsappBodyRef.current;
      if (textarea) {
        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const currentVal = localFormData.whatsappMessage || '';
        const newVal = currentVal.substring(0, start) + variableKey + currentVal.substring(end);
        setLocalFormData(prev => prev ? { ...prev, whatsappMessage: newVal } : null);
        setTimeout(() => {
          textarea.focus();
          textarea.setSelectionRange(start + variableKey.length, start + variableKey.length);
        }, 50);
      } else {
        setLocalFormData(prev => prev ? { ...prev, whatsappMessage: (prev.whatsappMessage || '') + ' ' + variableKey } : null);
      }
    }

    setValidationError(null);
    setOperationStatus(null);
  };

  const handleSaveCurrent = async () => {
    if (!canManageTemplates) return;

    setIsSaving(true);
    setValidationError(null);
    setOperationStatus(null);
    try {
      const saved = await saveNotificationTemplate(
        laravelBootstrap,
        localFormData.eventKey,
        editableTemplateFields(localFormData),
      );
      setNotificationTemplates(prev => prev.map(template => template.eventKey === saved.eventKey ? saved : template));
      setLocalFormData(saved);
      setOperationStatus('Saved');
    } catch (error: unknown) {
      const messages = error instanceof NotificationApiError
        ? Object.values(error.errors).flat()
        : [];
      setValidationError(messages.join(' ') || (error instanceof Error ? error.message : 'Notification template could not be saved.'));
    } finally {
      setIsSaving(false);
    }
  };

  const handleResetTemplates = async () => {
    if (!canManageTemplates || !window.confirm('Reset all notification templates to defaults?')) return;

    setIsResetting(true);
    setValidationError(null);
    setOperationStatus(null);
    try {
      const reset = await resetNotificationTemplates(laravelBootstrap);
      setNotificationTemplates(reset);
      const selected = reset.find(template => template.eventKey === selectedStatusId) ?? reset[0] ?? null;
      setLocalFormData(selected ? { ...selected } : null);
      setOperationStatus('Reset');
    } catch (error: unknown) {
      setValidationError(error instanceof Error ? error.message : 'Notification templates could not be reset.');
    } finally {
      setIsResetting(false);
    }
  };

  const handleSendTestNotification = async () => {
    setValidationError(null);
    if (activeChannel === 'whatsapp') {
      setIsTestModalOpen(false);
      setOperationStatus('Not connected');
      return;
    }

    if (!canSendTests) return;

    setIsTesting(true);
    setOperationStatus(null);
    try {
      await sendNotificationTest(
        laravelBootstrap,
        localFormData.eventKey,
        testRecipient,
      );
      setIsTestModalOpen(false);
      setOperationStatus('Queued');
    } catch (error: unknown) {
      setValidationError(error instanceof Error ? error.message : 'Notification test could not be queued.');
    } finally {
      setIsTesting(false);
    }
  };

  const previewReplacements: Record<string, string> = {
    '{order_number}': 'ORD-9845',
    '{order_total}': 'RM 439.00',
    '{order_date}': new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
    '{order_items_summary}': '3 items: Audio Pro ANC Earbuds x 1, Protective Case x 2',
    '{order_url}': 'https://ecom.jwaistudio.online/order-tracking',
    '{status_name}': localFormData.statusName,
    '{staff_note}': 'Parcel wrapped with premium anti-shock protective film.',
    '{customer_name}': 'Elena Rostova',
    '{customer_first_name}': 'Elena',
    '{customer_email}': 'elena.rostova@techlux.co',
    '{customer_phone}': '+6012-345 6789',
    '{payment_method}': 'Bank In / DuitNow (Manual Verification)',
    '{payment_reference}': 'DUITNOW-9845-TX',
    '{payment_proof_reason}': 'Transfer slip amount does not match order grand total.',
    '{shipping_address}': 'Level 18, Menara TechLux, Jalan Sultan Ismail, 50250 Kuala Lumpur',
    '{carrier_name}': 'J&T Express Malaysia',
    '{tracking_number}': 'JTE892410294MY',
    '{tracking_url}': 'https://ecom.jwaistudio.online/order-tracking',
    '{shop_name}': shopSettings.shopName || 'JW Studio',
    '{shop_email}': shopSettings.email || 'support@jwstudio.my',
    '{shop_phone}': shopSettings.phone || '+60 3-8888 1234',
    '{shop_whatsapp}': shopSettings.whatsapp || '+60 12-345 6789'
  };

  const renderSampleText = (value: string) => {
    let result = value || '';
    Object.entries(previewReplacements).forEach(([key, replacement]) => {
      result = result.split(key).join(replacement);
    });
    return result;
  };

  const colorBadgeClass = (color?: string) => {
    switch (color) {
      case 'emerald':
        return 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800';
      case 'blue':
        return 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/80 dark:text-blue-300 dark:border-blue-800';
      case 'amber':
        return 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/80 dark:text-amber-300 dark:border-amber-800';
      case 'rose':
        return 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/80 dark:text-rose-300 dark:border-rose-800';
      case 'purple':
        return 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/80 dark:text-purple-300 dark:border-purple-800';
      case 'cyan':
        return 'bg-cyan-100 text-cyan-800 border-cyan-300 dark:bg-cyan-950/80 dark:text-cyan-300 dark:border-cyan-800';
      default:
        return 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950/80 dark:text-indigo-300 dark:border-indigo-800';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header Banner */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/50 dark:border-indigo-800/50">
              <Mail className="w-5 h-5" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                Auto Email & WhatsApp Templates
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                  Real-Time Notification Hub
                </span>
              </h1>
              <p className="text-xs text-neutral-500 mt-0.5">
                Manage automated customer notification emails and WhatsApp messages triggered during order lifecycle and payment verification updates.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2.5 flex-wrap">
          {operationStatus && (
            <span className={`px-2.5 py-1 rounded-lg text-xs font-bold border ${
              operationStatus === 'Not connected'
                ? 'bg-neutral-100 text-neutral-700 border-neutral-300 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700'
                : 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-300 dark:border-emerald-800'
            }`}>
              {operationStatus}
            </span>
          )}
          <button
            type="button"
            onClick={() => setIsTestModalOpen(true)}
            disabled={!canSendTests}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold text-neutral-700 dark:text-neutral-300 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Send className="w-3.5 h-3.5 text-indigo-500" />
            <span>Send Test Notification</span>
          </button>

          <button
            type="button"
            onClick={() => void handleResetTemplates()}
            disabled={!canManageTemplates || isResetting}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 text-xs font-semibold text-neutral-700 dark:text-neutral-300 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <RotateCcw className="w-3.5 h-3.5 text-neutral-400" />
            <span>Reset to Defaults</span>
          </button>

          <button
            type="button"
            onClick={() => void handleSaveCurrent()}
            disabled={!canManageTemplates || isSaving}
            className="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Save className="w-3.5 h-3.5" />
            <span>Save Status Template</span>
          </button>
        </div>
      </div>

      {validationError && (
        <div className="flex items-start gap-2.5 px-4 py-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300 text-xs font-semibold">
          <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{validationError}</span>
        </div>
      )}

      {/* Order Status Navigation Matrix */}
      <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4 shadow-xs">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-neutral-500" />
            <h3 className="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
              Select Order Status Trigger
            </h3>
          </div>
          <span className="text-[11px] text-neutral-400">
            {allTemplates.length} lifecycle statuses configured
          </span>
        </div>

        <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
          {allTemplates.map(status => {
            const isSelected = status.eventKey === selectedStatusId;
            return (
              <button
                key={status.eventKey}
                onClick={() => {
                  setSelectedStatusId(status.eventKey);
                  setValidationError(null);
                  setOperationStatus(null);
                }}
                className={`flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl border text-xs font-semibold whitespace-nowrap transition cursor-pointer ${
                  isSelected
                    ? 'bg-neutral-900 text-white border-neutral-900 dark:bg-neutral-100 dark:text-neutral-900 dark:border-neutral-100 shadow-xs'
                    : 'bg-neutral-50 dark:bg-neutral-800/40 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700'
                }`}
              >
                <span className={`w-2 h-2 rounded-full ${
                  status.color === 'emerald' ? 'bg-emerald-500' :
                  status.color === 'blue' ? 'bg-blue-500' :
                  status.color === 'amber' ? 'bg-amber-500' :
                  status.color === 'rose' ? 'bg-rose-500' :
                  status.color === 'cyan' ? 'bg-cyan-500' :
                  status.color === 'purple' ? 'bg-purple-500' : 'bg-indigo-500'
                }`} />
                <span>{status.statusName}</span>
                {!status.automaticEmail && (
                  <span className="px-1.5 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                    DORMANT
                  </span>
                )}
                <div className="flex items-center gap-1 opacity-75 text-[10px]">
                  {status.emailEnabled && <span>✉️</span>}
                  {status.whatsappEnabled && <span>💬</span>}
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Main Grid: Editor (Left 7 Cols) + Live Interactive Preview (Right 5 Cols) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Column: Template Form & Variables Palette */}
        <div className="lg:col-span-7 space-y-6">
          {/* Channel Tabs */}
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-2 shadow-xs flex items-center justify-between">
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={() => setActiveChannel('email')}
                className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer ${
                  activeChannel === 'email'
                    ? 'bg-indigo-600 text-white shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                <Mail className="w-4 h-4" />
                <span>Email Template</span>
                {localFormData.emailEnabled ? (
                  <span className="w-2 h-2 rounded-full bg-emerald-400" />
                ) : (
                  <span className="w-2 h-2 rounded-full bg-neutral-400" />
                )}
              </button>

              <button
                type="button"
                onClick={() => setActiveChannel('whatsapp')}
                className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer ${
                  activeChannel === 'whatsapp'
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                }`}
              >
                <MessageSquare className="w-4 h-4" />
                <span>WhatsApp Message</span>
                {localFormData.whatsappEnabled ? (
                  <span className="w-2 h-2 rounded-full bg-emerald-400" />
                ) : (
                  <span className="w-2 h-2 rounded-full bg-neutral-400" />
                )}
              </button>
            </div>

            <div className="pr-3">
              <span className={`px-2.5 py-1 rounded-lg text-xs font-bold border ${colorBadgeClass(localFormData.color)}`}>
                Trigger: {localFormData.statusName}
              </span>
            </div>
          </div>

          {/* Dynamic Variable Chips Palette */}
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-4 shadow-xs space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Tag className="w-4 h-4 text-indigo-500" />
                <h4 className="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
                  Dynamic Placeholders & Variables
                </h4>
              </div>
              <div className="flex items-center gap-1 text-[11px]">
                {['all', 'order', 'customer', 'payment', 'shipping', 'store'].map(cat => (
                  <button
                    key={cat}
                    type="button"
                    onClick={() => setSelectedVarCategory(cat)}
                    className={`px-2 py-0.5 rounded-md capitalize transition cursor-pointer ${
                      selectedVarCategory === cat
                        ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900 font-bold'
                        : 'text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800'
                    }`}
                  >
                    {cat}
                  </button>
                ))}
              </div>
            </div>

            <p className="text-[11px] text-neutral-500">
              Click any variable chip below to insert it at your current cursor position:
            </p>

            <div className="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto pr-1">
              {filteredVariables.map(v => (
                <button
                  key={v.key}
                  type="button"
                  onClick={() => handleInsertVariable(v.key)}
                  disabled={!canManageTemplates}
                  className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:border-indigo-300 dark:hover:border-indigo-800 text-[11px] font-mono text-neutral-700 dark:text-neutral-300 transition cursor-pointer group disabled:opacity-50 disabled:cursor-not-allowed"
                  title={`${v.label} (Example: ${v.example})`}
                >
                  <Plus className="w-3 h-3 text-indigo-500 group-hover:scale-125 transition-transform" />
                  <span className="font-semibold text-indigo-600 dark:text-indigo-400">{v.key}</span>
                  <span className="text-neutral-400 text-[10px]">({v.label})</span>
                </button>
              ))}
            </div>
          </div>

          {/* EMAIL TAB CONTENT */}
          {activeChannel === 'email' && (
            <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-800">
                <div>
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                    Email Notification Dispatch Rule
                  </h3>
                  <p className="text-xs text-neutral-500">
                    {localFormData.automaticEmail
                      ? `Sent automatically when an admin updates the order status to "${localFormData.statusName}".`
                      : 'Email automation is dormant for this event; the saved Email state remains disabled.'}
                  </p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={localFormData.emailEnabled}
                    onChange={e => setLocalFormData(prev => prev ? { ...prev, emailEnabled: e.target.checked } : null)}
                    disabled={!canManageTemplates || !localFormData.automaticEmail}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-neutral-200 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-neutral-600 peer-checked:bg-indigo-600"></div>
                </label>
              </div>

              {/* Subject */}
              <div>
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Email Subject Line
                </label>
                <input
                  ref={emailSubjectRef}
                  type="text"
                  onFocus={() => setLastFocusedField('subject')}
                  value={localFormData.emailSubject}
                  onChange={e => setLocalFormData(prev => prev ? { ...prev, emailSubject: e.target.value } : null)}
                  disabled={!canManageTemplates}
                  placeholder="e.g. [{shop_name}] Order #{order_number} Update: {status_name}"
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-hidden font-mono"
                />
              </div>

              {/* Preheader */}
              <div>
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Inbox Preview Text (Preheader)
                </label>
                <input
                  type="text"
                  value={localFormData.emailPreheader}
                  onChange={e => setLocalFormData(prev => prev ? { ...prev, emailPreheader: e.target.value } : null)}
                  disabled={!canManageTemplates}
                  placeholder="Brief preview line shown next to subject in Gmail/Outlook"
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-hidden"
                />
              </div>

              {/* Heading Title */}
              <div>
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Email Headline Banner
                </label>
                <input
                  type="text"
                  value={localFormData.emailHeading}
                  onChange={e => setLocalFormData(prev => prev ? { ...prev, emailHeading: e.target.value } : null)}
                  disabled={!canManageTemplates}
                  placeholder="e.g. Order Confirmation & Payment Instructions"
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-hidden"
                />
              </div>

              {/* Body */}
              <div>
                <div className="flex items-center justify-between mb-1">
                  <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    Email Message Content
                  </label>
                  <span className="text-[11px] text-neutral-400">
                    Supports line breaks and variables
                  </span>
                </div>
                <textarea
                  ref={emailBodyRef}
                  onFocus={() => setLastFocusedField('emailBody')}
                  rows={8}
                  value={localFormData.emailBody}
                  onChange={e => setLocalFormData(prev => prev ? { ...prev, emailBody: e.target.value } : null)}
                  disabled={!canManageTemplates}
                  placeholder="Write your email body message here..."
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-hidden resize-y"
                />
              </div>

              {/* CTA Button */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-800">
                <div>
                  <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                    Call To Action Button Label
                  </label>
                  <input
                    type="text"
                    value={localFormData.emailCtaText}
                    onChange={e => setLocalFormData(prev => prev ? { ...prev, emailCtaText: e.target.value } : null)}
                    disabled={!canManageTemplates}
                    placeholder="e.g. Track Order"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-medium text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-hidden"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                    CTA Button Destination URL
                  </label>
                  <input
                    type="text"
                    value={localFormData.emailCtaUrl}
                    onChange={e => setLocalFormData(prev => prev ? { ...prev, emailCtaUrl: e.target.value } : null)}
                    disabled={!canManageTemplates}
                    placeholder="e.g. {order_url} or {tracking_url}"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-hidden"
                  />
                </div>
              </div>
            </div>
          )}

          {/* WHATSAPP TAB CONTENT */}
          {activeChannel === 'whatsapp' && (
            <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 p-5 shadow-xs space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-neutral-200 dark:border-neutral-800">
                <div>
                  <h3 className="text-sm font-bold text-neutral-900 dark:text-white flex items-center gap-2">
                    WhatsApp Message Dispatch Rule
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 border border-neutral-200 dark:border-neutral-700">
                      Not connected
                    </span>
                  </h3>
                  <p className="text-xs text-neutral-500">
                    Editing and preview remain available. WhatsApp delivery is not connected in this phase.
                  </p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={localFormData.whatsappEnabled}
                    onChange={e => setLocalFormData(prev => prev ? { ...prev, whatsappEnabled: e.target.checked } : null)}
                    disabled={!canManageTemplates}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-neutral-200 peer-focus:outline-hidden rounded-full peer dark:bg-neutral-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-neutral-600 peer-checked:bg-emerald-600"></div>
                </label>
              </div>

              {/* Message text */}
              <div>
                <div className="flex items-center justify-between mb-1">
                  <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    WhatsApp Message Text
                  </label>
                  <div className="flex items-center gap-2 text-[11px] text-neutral-400 font-mono">
                    <span>*bold*</span>
                    <span>_italic_</span>
                    <span>~strike~</span>
                  </div>
                </div>
                <textarea
                  ref={whatsappBodyRef}
                  onFocus={() => setLastFocusedField('whatsappBody')}
                  rows={9}
                  value={localFormData.whatsappMessage}
                  onChange={e => setLocalFormData(prev => prev ? { ...prev, whatsappMessage: e.target.value } : null)}
                  disabled={!canManageTemplates}
                  placeholder="Hi *{customer_name}*, your order #{order_number} has been updated..."
                  className="w-full px-3.5 py-2.5 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs font-mono text-neutral-900 dark:text-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-hidden resize-y"
                />
              </div>

              {/* Interactive Buttons Config */}
              <div className="space-y-2 pt-2 border-t border-neutral-200 dark:border-neutral-800">
                <div className="flex items-center justify-between">
                  <label className="text-xs font-bold text-neutral-700 dark:text-neutral-300">
                    WhatsApp Quick Action Buttons
                  </label>
                  <button
                    type="button"
                    disabled={!canManageTemplates}
                    onClick={() => {
                      const newBtn = {
                        id: `btn_${Date.now()}`,
                        text: '🔗 Open Link',
                        type: 'url' as const,
                        value: '{order_url}'
                      };
                      setLocalFormData(prev => prev ? {
                        ...prev,
                        whatsappCtaButtons: [...(prev.whatsappCtaButtons || []), newBtn]
                      } : null);
                    }}
                    className="flex items-center gap-1 px-2.5 py-1 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 text-neutral-700 dark:text-neutral-300 text-[11px] font-semibold transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <Plus className="w-3 h-3" />
                    <span>Add Button</span>
                  </button>
                </div>

                <div className="space-y-2">
                  {(localFormData.whatsappCtaButtons || []).map((btn, idx) => (
                    <div key={btn.id} className="flex items-center gap-2 p-2 rounded-xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 text-xs">
                      <span className="font-mono text-[10px] text-neutral-400">#{idx + 1}</span>
                      <input
                        type="text"
                        value={btn.text}
                        disabled={!canManageTemplates}
                        onChange={e => {
                          const updated = (localFormData.whatsappCtaButtons || []).map(b => b.id === btn.id ? { ...b, text: e.target.value } : b);
                          setLocalFormData(prev => prev ? { ...prev, whatsappCtaButtons: updated } : null);
                        }}
                        placeholder="Button Title (e.g. 📦 View Order)"
                        className="flex-1 px-2 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-xs text-neutral-900 dark:text-white"
                      />
                      <input
                        type="text"
                        value={btn.value}
                        disabled={!canManageTemplates}
                        onChange={e => {
                          const updated = (localFormData.whatsappCtaButtons || []).map(b => b.id === btn.id ? { ...b, value: e.target.value } : b);
                          setLocalFormData(prev => prev ? { ...prev, whatsappCtaButtons: updated } : null);
                        }}
                        placeholder="URL or Variable (e.g. {order_url})"
                        className="flex-1 px-2 py-1 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-xs font-mono text-neutral-900 dark:text-white"
                      />
                      <button
                        type="button"
                        disabled={!canManageTemplates}
                        onClick={() => {
                          const updated = (localFormData.whatsappCtaButtons || []).filter(b => b.id !== btn.id);
                          setLocalFormData(prev => prev ? { ...prev, whatsappCtaButtons: updated } : null);
                        }}
                        className="p-1 text-neutral-400 hover:text-rose-500 transition cursor-pointer"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  ))}
                  {(!localFormData.whatsappCtaButtons || localFormData.whatsappCtaButtons.length === 0) && (
                    <p className="text-[11px] text-neutral-400 italic">
                      No action buttons configured. You can include links directly in the message text.
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Right Column: Live Interactive Device Preview */}
        <div className="lg:col-span-5 space-y-4">
          <div className="flex items-center justify-between px-1">
            <div className="flex items-center gap-1.5">
              <Eye className="w-4 h-4 text-indigo-500" />
              <h3 className="text-xs font-bold uppercase tracking-wider text-neutral-800 dark:text-neutral-200">
                Live Customer Preview
              </h3>
            </div>

            {activeChannel === 'email' && (
              <div className="flex items-center gap-1 p-1 bg-neutral-100 dark:bg-neutral-800 rounded-lg text-xs">
                <button
                  type="button"
                  onClick={() => setPreviewDevice('desktop')}
                  className={`p-1.5 rounded-md transition cursor-pointer ${
                    previewDevice === 'desktop' ? 'bg-white dark:bg-neutral-700 text-indigo-600 shadow-xs' : 'text-neutral-500'
                  }`}
                  title="Desktop Preview"
                >
                  <Monitor className="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setPreviewDevice('mobile')}
                  className={`p-1.5 rounded-md transition cursor-pointer ${
                    previewDevice === 'mobile' ? 'bg-white dark:bg-neutral-700 text-indigo-600 shadow-xs' : 'text-neutral-500'
                  }`}
                  title="Mobile Preview"
                >
                  <Smartphone className="w-3.5 h-3.5" />
                </button>
              </div>
            )}
          </div>

          {/* EMAIL LIVE PREVIEW */}
          {activeChannel === 'email' && (
            <div className={`transition-all mx-auto ${previewDevice === 'mobile' ? 'max-w-[340px]' : 'w-full'}`}>
              <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 shadow-lg overflow-hidden">
                {/* Simulated Email Client Top Bar */}
                <div className="p-3 bg-neutral-100 dark:bg-neutral-800/80 border-b border-neutral-200 dark:border-neutral-800 space-y-1.5">
                  <div className="flex items-center justify-between text-[10px] text-neutral-400">
                    <span className="font-mono">From: {shopSettings.shopName || 'JW Studio'} &lt;noreply@jwstudio.my&gt;</span>
                    <span>Just now</span>
                  </div>
                  <div className="font-bold text-xs text-neutral-900 dark:text-white truncate">
                    {renderSampleText(localFormData.emailSubject) || '(Subject line empty)'}
                  </div>
                  <div className="text-[11px] text-neutral-500 truncate">
                    {renderSampleText(localFormData.emailPreheader) || '(Preheader preview empty)'}
                  </div>
                </div>

                {/* Email Body Container */}
                <div className="p-5 bg-neutral-50 dark:bg-neutral-950/40 space-y-4">
                  {/* Store Header */}
                  <div className="text-center pb-4 border-b border-neutral-200 dark:border-neutral-800">
                    {shopSettings.shopLogo ? (
                      <img
                        src={shopSettings.shopLogo}
                        alt="Store Logo"
                        className="h-9 mx-auto object-contain"
                      />
                    ) : (
                      <div className="font-extrabold text-sm tracking-wider text-neutral-900 dark:text-white uppercase">
                        {shopSettings.shopName || 'JW STUDIO'}
                      </div>
                    )}
                    <span className="text-[10px] text-neutral-400 uppercase tracking-wider block mt-1">
                      Official Order Notification
                    </span>
                  </div>

                  {/* Headline Banner Card */}
                  <div className="p-3.5 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-center space-y-1 shadow-xs">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${colorBadgeClass(localFormData.color)}`}>
                      {localFormData.statusName}
                    </span>
                    <h4 className="text-sm font-bold text-neutral-900 dark:text-white">
                      {renderSampleText(localFormData.emailHeading)}
                    </h4>
                  </div>

                  {/* Formatted Body */}
                  <div className="p-4 rounded-xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 text-xs text-neutral-700 dark:text-neutral-300 whitespace-pre-line leading-relaxed shadow-xs">
                    {renderSampleText(localFormData.emailBody)}
                  </div>

                  {/* CTA Button */}
                  {localFormData.emailCtaText && (
                    <div className="text-center pt-1">
                      <a
                        href="#preview"
                        onClick={e => e.preventDefault()}
                        className="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md transition"
                      >
                        <span>{renderSampleText(localFormData.emailCtaText)}</span>
                        <ArrowRight className="w-3.5 h-3.5" />
                      </a>
                    </div>
                  )}

                  {/* Footer */}
                  <div className="pt-4 border-t border-neutral-200 dark:border-neutral-800 text-center space-y-1 text-[10px] text-neutral-400">
                    <p>© {new Date().getFullYear()} {shopSettings.shopName || 'JW Studio'}. All rights reserved.</p>
                    <p>Questions? Contact our support team at {shopSettings.email || 'support@jwstudio.my'}</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* WHATSAPP LIVE PREVIEW */}
          {activeChannel === 'whatsapp' && (
            <div className="max-w-[340px] mx-auto">
              <div className="rounded-3xl border-4 border-neutral-800 dark:border-neutral-700 shadow-2xl overflow-hidden bg-[#ECE5DD] dark:bg-[#0b141a]">
                {/* WhatsApp Chat Header */}
                <div className="px-3.5 py-3 bg-[#075E54] dark:bg-[#1f2c34] text-white flex items-center gap-2.5 shadow-sm">
                  <div className="w-8 h-8 rounded-full bg-emerald-700 flex items-center justify-center font-bold text-xs text-white border border-emerald-400/40">
                    JW
                  </div>
                  <div className="flex-1 truncate">
                    <div className="text-xs font-bold truncate flex items-center gap-1">
                      <span>{shopSettings.shopName || 'JW Studio'}</span>
                      <CheckCircle2 className="w-3 h-3 text-emerald-300 inline" />
                    </div>
                    <span className="text-[10px] text-emerald-100 opacity-80 block">
                      Official Business Account
                    </span>
                  </div>
                </div>

                {/* WhatsApp Chat Area */}
                <div className="p-3.5 space-y-3 min-h-[380px] flex flex-col justify-end">
                  {/* System Date Pill */}
                  <div className="text-center">
                    <span className="px-2.5 py-1 rounded-md bg-white/70 dark:bg-neutral-800/80 text-[10px] font-semibold text-neutral-600 dark:text-neutral-300 shadow-xs">
                      TODAY
                    </span>
                  </div>

                  {/* Outgoing Business Bubble */}
                  <div className="space-y-1 max-w-[92%] self-start">
                    <div className="p-3 rounded-2xl rounded-tl-xs bg-white dark:bg-[#1f2c34] text-neutral-900 dark:text-neutral-100 shadow-md text-xs whitespace-pre-line leading-relaxed">
                      {renderSampleText(localFormData.whatsappMessage)}
                      <div className="flex items-center justify-end gap-1 mt-1.5 text-[9px] text-neutral-400">
                        <span>10:42 AM</span>
                        <span className="text-emerald-500 font-bold">✓✓</span>
                      </div>
                    </div>

                    {/* WhatsApp CTA Action Buttons */}
                    {(localFormData.whatsappCtaButtons || []).map(btn => (
                      <button
                        key={btn.id}
                        type="button"
                        className="w-full py-2 px-3 rounded-xl bg-white dark:bg-[#1f2c34] text-emerald-600 dark:text-emerald-400 text-xs font-bold border border-neutral-200/60 dark:border-neutral-700/60 shadow-xs flex items-center justify-center gap-1.5 hover:bg-neutral-50 transition cursor-pointer"
                      >
                        <ExternalLink className="w-3 h-3" />
                        <span>{renderSampleText(btn.text)}</span>
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Test Dispatch Modal */}
      {isTestModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
          <div className="bg-white dark:bg-neutral-900 rounded-2xl border border-neutral-200 dark:border-neutral-800 w-full max-w-md p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Send className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                <h3 className="text-base font-bold text-neutral-900 dark:text-white">
                  Send Test Notification
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setIsTestModalOpen(false)}
                className="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 cursor-pointer"
              >
                ✕
              </button>
            </div>

            <p className="text-xs text-neutral-500">
              {activeChannel === 'email' ? (
                <>Queues a real test of the <strong>{localFormData.statusName}</strong> Email template populated with test order details.</>
              ) : (
                <>The <strong>{localFormData.statusName}</strong> WhatsApp template remains previewable, but delivery is <strong>Not connected</strong>.</>
              )}
            </p>

            {activeChannel === 'email' ? (
              <div>
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Recipient Email Address
                </label>
                <input
                  type="email"
                  value={testRecipient}
                  onChange={e => setTestRecipient(e.target.value)}
                  disabled={isTesting}
                  placeholder="admin@jwstudio.my"
                  className="w-full px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white"
                />
              </div>
            ) : (
              <div>
                <label className="block text-xs font-bold text-neutral-700 dark:text-neutral-300 mb-1">
                  Recipient WhatsApp Phone (+Country Code)
                </label>
                <input
                  type="text"
                  value={testPhone}
                  onChange={e => setTestPhone(e.target.value)}
                  placeholder="+60 12-345 6789"
                  className="w-full px-3.5 py-2 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-white font-mono"
                />
              </div>
            )}

            <div className="flex items-center justify-end gap-2 pt-3 border-t border-neutral-200 dark:border-neutral-800">
              <button
                type="button"
                onClick={() => setIsTestModalOpen(false)}
                className="px-4 py-2 rounded-xl text-xs font-semibold text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => void handleSendTestNotification()}
                disabled={activeChannel === 'email' && (!canSendTests || isTesting)}
                className="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Send className="w-3.5 h-3.5" />
                <span>Send Test</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
