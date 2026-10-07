export type SupportChannel = 
  | 'whatsapp'
  | 'web_guest'
  | 'facebook'
  | 'instagram'
  | 'tiktok'
  | 'reddit'
  | 'x';

export type ConversationStatus = 'open' | 'waiting_human' | 'human_mode' | 'resolved' | 'archived';

export type PriorityLevel = 'low' | 'normal' | 'high' | 'urgent';

export type LeadQualification = 
  | 'vip_buyer'
  | 'qualified_lead'
  | 'hot_prospect'
  | 'wholesale_inquiry'
  | 'support_ticket'
  | 'general_guest';

export type SenderRole = 'customer' | 'ai_agent' | 'human_agent' | 'system';

export type MessageType = 
  | 'text' 
  | 'image' 
  | 'video' 
  | 'document' 
  | 'voice' 
  | 'product_card' 
  | 'order_card' 
  | 'system_event';

export type MessageDeliveryStatus = 'pending' | 'sending' | 'accepted' | 'sent' | 'delivered' | 'read' | 'failed' | 'unknown';

export interface MessageAttachment {
  analysis?: {status:'pending'|'processing'|'ready'|'failed'|'unsupported'|'expired';text?:string|null;limitations:string[];needsConfirmation:boolean;failureCode?:string|null;retryUrl?:string|null}|null;
  id: string;
  name: string;
  url: string;
  type: 'image' | 'video' | 'document' | 'audio';
  sizeBytes?: number;
  thumbnailUrl?: string;
  durationSeconds?: number;
  transcription?: string;
  status?: 'pending' | 'ready' | 'failed';
  error?: string | null;
  retryUrl?: string | null;
}

export interface ChatMessage {
  id: string;
  conversationId: string;
  senderRole: SenderRole;
  senderName: string;
  senderAvatar?: string;
  timestamp: string;
  content: string;
  type: MessageType;
  attachments?: MessageAttachment[];
  deliveryStatus?: MessageDeliveryStatus;
  sendError?: string | null;
  clientMessageId?: string;
  aiTrace?: {coverage?:{index:number;status:string;source_ids:string[]}[];followUpCount?:number;version:number;settingsVersion:number;language:string;decision:string;clarification:string|null;escalation:string|null;tools:{tool:string;status:string;sourceIds:string[]}[];retrievedSourceIds:string[];citedSourceIds:string[]} | null;
  aiMetadata?: {
    confidenceScore?: number;
    reasoningSteps?: string[];
    intent?: string;
    modelUsed?: string;
    suggestedReplies?: string[];
  };
  productCard?: {
    id: string;
    name: string;
    price: number;
    image: string;
    stock: number;
    sku: string;
  };
  orderCard?: {
    orderId: string;
    status: string;
    totalAmount: number;
    itemsCount: number;
    courierName?: string;
    trackingNumber?: string;
  };
}

export interface AIDraft {
  id: string;
  content: string;
  suggestedAt: string;
  confidence: number;
  reasoning: string;
  model: string;
  sourceContext: string;
}

export interface CustomerProfile {
  id: string;
  name: string;
  avatar: string;
  email?: string;
  phone?: string;
  handle?: string;
  platformUserId?: string | null;
  location?: string;
  countryCode?: string;
  language: string;
  timezone?: string;
  totalOrdersCount: number;
  lifetimeValue: number;
  firstSeenAt: string;
  lastSeenAt: string;
  tags: string[];
  leadScore: number; // 0 - 100
  leadStage: 'Discovery' | 'Evaluation' | 'Checkout Ready' | 'Active Customer' | 'VIP At Risk';
  qualification: LeadQualification;
  recentOrderId?: string;
  recentOrderTotal?: number;
  recentOrderStatus?: string;
  recentOrderTracking?: string;
}

export interface InternalStaffNote {
  id: string;
  authorName: string;
  authorAvatar: string;
  timestamp: string;
  content: string;
}

export interface SupportConversation {
  replyCadence?: {id:string;status:string;publishedSegments:number;totalSegments:number;stopReason:string|null}|null;
  handoffBrief?: {customerRequests:string[];checks:{tool:string;status:string}[];reason:string;missingContext:string|null;unresolvedTopics:number[];handoffStatus:string}|null;
  mediaCapabilities?: {images:boolean;documents:boolean;audio:boolean};
  canReply?: boolean;
  replyBlockedReason?: string | null;
  replyWindowExpiresAt?: string | null;
  pageName?: string | null;
  isArchived?: boolean;
  id: string;
  channel: SupportChannel;
  customer: CustomerProfile;
  title: string;
  snippet: string;
  unreadCount: number;
  isPinned: boolean;
  priority: PriorityLevel;
  status: ConversationStatus; // 'open' (AI Handling) | 'human_mode' | 'resolved'
  assignedAgent?: {
    id: string;
    name: string;
    avatar: string;
    isLocked: boolean; // Locked by teammate banner
  };
  aiSummary: {
    intent: string;
    sentiment: 'positive' | 'neutral' | 'frustrated' | 'inquisitive';
    keyIssue: string;
    suggestedAction: string;
    summaryMarkdown: string;
  };
  activeAiDraft?: AIDraft | null;
  isAiTyping?: boolean;
  lastMessageAt: string;
  lastMessageTimestamp?: string;
  messages: ChatMessage[];
  internalNotes: InternalStaffNote[];
  createdAt: string;
  customMetadata?: {
    sourceUrl?: string;
    userAgent?: string;
    utmCampaign?: string;
    adId?: string;
    referrer?: string;
  };
}

// ----------------------------------------
// Internal Copilot Mode Types
// ----------------------------------------

export type CopilotReasoningLevel = 'flash' | 'deep_reasoning';

export interface CopilotQueryMessage {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: string;
  reasoningSteps?: string[];
  suggestedFollowUps?: string[];
  dataReferences?: {
    type: 'sales_metric' | 'inventory_alert' | 'courier_sla' | 'customer_churn' | 'revenue_chart';
    title: string;
    value: string;
    change?: string;
  }[];
  isStreaming?: boolean;
}

export interface CannedResponse {
  id: string;
  shortcut: string; // e.g. "/shipping", "/refund"
  title: string;
  category: 'Shipping' | 'Payment' | 'Returns' | 'Discounts' | 'Greeting';
  content: string;
}

