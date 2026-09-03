import { describe, expect, it } from 'vitest';
import { manualRefundConfirmationRequirement, warehouseNoteRequirement } from './OrderReturnRequestCard';

describe('warehouseNoteRequirement', () => {
  it('allows a restockable return to be received without a note', () => {
    expect(warehouseNoteRequirement('restockable', '')).toEqual({ required: false, valid: true });
  });

  it('requires a note when damaged stock is written off', () => {
    expect(warehouseNoteRequirement('damaged', '')).toEqual({ required: true, valid: false });
    expect(warehouseNoteRequirement('damaged', 'Cracked housing at intake.')).toEqual({ required: true, valid: true });
  });
});

describe('manualRefundConfirmationRequirement', () => {
  it('requires both the external reference and staff audit note', () => {
    expect(manualRefundConfirmationRequirement('', '')).toEqual({ valid: false });
    expect(manualRefundConfirmationRequirement('STRIPE-1', '')).toEqual({ valid: false });
    expect(manualRefundConfirmationRequirement('STRIPE-1', 'Completed in Stripe Dashboard.')).toEqual({ valid: true });
  });
});
