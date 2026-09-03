import { describe, expect, it } from 'vitest';
import { warehouseNoteRequirement } from './OrderReturnRequestCard';

describe('warehouseNoteRequirement', () => {
  it('allows a restockable return to be received without a note', () => {
    expect(warehouseNoteRequirement('restockable', '')).toEqual({ required: false, valid: true });
  });

  it('requires a note when damaged stock is written off', () => {
    expect(warehouseNoteRequirement('damaged', '')).toEqual({ required: true, valid: false });
    expect(warehouseNoteRequirement('damaged', 'Cracked housing at intake.')).toEqual({ required: true, valid: true });
  });
});
