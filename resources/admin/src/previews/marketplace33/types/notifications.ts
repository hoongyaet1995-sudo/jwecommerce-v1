export interface StatusNotificationTemplate {
  id: string;
  statusId: string;
  statusCode: string;
  statusName: string;
  color?: string;
  // Email Channel
  emailEnabled: boolean;
  emailSubject: string;
  emailPreheader: string;
  emailHeading: string;
  emailBody: string;
  emailCtaText: string;
  emailCtaUrl: string;
  // WhatsApp Channel
  whatsappEnabled: boolean;
  whatsappMessage: string;
  whatsappCtaButtons?: {
    id: string;
    text: string;
    type: 'url' | 'call' | 'quick_reply';
    value: string;
  }[];
  updatedAt?: string;
  updatedBy?: string;
}

export interface NotificationVariable {
  key: string;
  label: string;
  example: string;
  category: 'order' | 'customer' | 'payment' | 'shipping' | 'store';
}
