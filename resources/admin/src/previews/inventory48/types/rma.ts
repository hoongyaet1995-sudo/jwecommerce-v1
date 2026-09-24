export type RmaStatus = 'requested' | 'approved' | 'received' | 'resolved' | 'rejected';
export type RmaOutcome = 'refund' | 'replacement';
export type RmaItemCondition = 'restockable' | 'damaged';

export interface ReturnAuditEvent {
  id: string;
  timestamp: string;
  action: string;
  actor: string;
  note?: string;
}

export interface ReturnRequest {
  id: string;
  rmaNumber: string; // e.g. "RMA-2026-0041"
  status: RmaStatus;
  requestedAt: string;
  reviewedAt?: string;
  reviewedBy?: string;
  receivedAt?: string;
  receivedBy?: string;
  resolvedAt?: string;
  resolvedBy?: string;
  
  // Customer & affected order item
  orderItemId: string;
  itemTitle: string;
  itemVariantTitle?: string;
  itemSku: string;
  itemPrice: number;
  itemImage: string;
  quantity: number; // exact approved quantity
  
  // Requested outcome
  requestedOutcome: RmaOutcome; // 'refund' | 'replacement'
  refundAmount?: number;
  
  // Customer reason, notes, and evidence
  reason: string;
  customerNotes?: string;
  evidenceImages?: string[];
  
  // Rejection details if rejected
  rejectionReason?: string; // customer-visible reason
  
  // Receiving inspection details
  receivedCondition?: RmaItemCondition; // 'restockable' | 'damaged'
  receivingNotes?: string; // optional internal note
  
  // Resolution details
  resolutionNotes?: string;
  replacementOrderId?: string;
  
  // Short audit timeline
  auditTimeline: ReturnAuditEvent[];
}
