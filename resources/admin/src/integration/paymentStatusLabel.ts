export const paymentStatusLabel = (status: string): string => ({paid:'Paid',pending:'Pending',refunded:'Refunded',partially_refunded:'Partially Refunded',failed:'Failed'}[status] ?? 'Unknown');
