export type NotificationEventKey =
  | 'order_created'
  | 'payment_verified'
  | 'payment_proof_rejected'
  | 'ready_to_ship'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'on_hold';

export type NotificationPermission =
  | 'notifications.templates.view'
  | 'notifications.templates.manage'
  | 'notifications.test.send'
  | 'notifications.deliveries.view'
  | 'notifications.deliveries.retry'
  | 'notifications.deliveries.resend'
  | 'notifications.current.send';

export interface WhatsAppCtaButton {
  id: string;
  text: string;
  type: 'url';
  value: string;
}

export interface NotificationTemplate {
  eventKey: NotificationEventKey;
  statusName: string;
  color: string;
  automaticEmail: boolean;
  variables: string[];
  emailEnabled: boolean;
  emailSubject: string;
  emailPreheader: string;
  emailHeading: string;
  emailBody: string;
  emailCtaText: string;
  emailCtaUrl: string;
  whatsappEnabled: boolean;
  whatsappMessage: string;
  whatsappCtaButtons: WhatsAppCtaButton[];
  version: number;
  updatedById: number | null;
  updatedAt: string | null;
}

export type NotificationTemplateUpdate = Partial<Pick<NotificationTemplate,
  | 'emailEnabled'
  | 'emailSubject'
  | 'emailPreheader'
  | 'emailHeading'
  | 'emailBody'
  | 'emailCtaText'
  | 'emailCtaUrl'
  | 'whatsappEnabled'
  | 'whatsappMessage'
  | 'whatsappCtaButtons'
  | 'version'
>>;

export type NotificationDispatchChannel = 'email';
export type NotificationDispatchState = 'queued' | 'sending' | 'sent' | 'failed';
export type NotificationDispatchSource = 'automatic' | 'admin_current_template' | 'manual_resend' | 'test';

export interface NotificationDispatch {
  id: string;
  eventKey: NotificationEventKey;
  channel: NotificationDispatchChannel;
  state: NotificationDispatchState;
  source: NotificationDispatchSource;
  subject: string;
  templateVersion: number | null;
  attemptCount: number;
  queuedAt: string | null;
  sentAt: string | null;
  failedAt: string | null;
  errorCode: string | null;
  resendOfId: string | null;
  createdAt: string | null;
}

export interface OrderNotificationPreview {
  eventKey: NotificationEventKey;
  subject: string;
  preheader: string;
  heading: string;
  body: string;
  ctaText: string;
  ctaUrl: string;
  variables: Record<string, string>;
}

export interface OrderNotificationAction {
  eventKey: NotificationEventKey;
  staffNote?: string | null;
  paymentProofReason?: string | null;
}

export type NotificationValidationErrors = Record<string, string[]>;

export class NotificationApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors: NotificationValidationErrors = {},
  ) {
    super(message);
    this.name = 'NotificationApiError';
  }
}
