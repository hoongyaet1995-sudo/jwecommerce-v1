import React from 'react';
import { IndependentStockAllocationModal } from './IndependentStockAllocationModal';
import { InitialStockCountModalProps, SharedInitialStockCountModal } from './SharedInitialStockCountModal';

export const InitialStockCountModal: React.FC<InitialStockCountModalProps> = props => {
  if (props.mode === 'independent') {
    return <IndependentStockAllocationModal {...props} mode="independent" />;
  }

  return <SharedInitialStockCountModal {...props} />;
};
