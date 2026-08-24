import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import { DeleteConfirmationModal } from './DeleteConfirmationModal';

const renderModal = (open: boolean) => renderToStaticMarkup(
  <DeleteConfirmationModal
    isOpen={open}
    itemType="Product"
    itemName="Reference Headphones"
    itemDetails="3 SKUs • $299.00"
    warningMessage="Removing this product removes it from the storefront."
    impactItems={['3 SKU variants will be removed.', 'Existing order history remains intact.']}
    onConfirm={vi.fn()}
    onClose={vi.fn()}
  />,
);

describe('DeleteConfirmationModal', () => {
  it('does not render destructive content while closed', () => {
    expect(renderModal(false)).toBe('');
  });

  it('renders the requested item facts and explicit destructive action while open', () => {
    const html = renderModal(true);

    expect(html).toContain('Delete Product?');
    expect(html).toContain('Reference Headphones');
    expect(html).toContain('3 SKUs • $299.00');
    expect(html).toContain('3 SKU variants will be removed.');
    expect(html).toContain('Yes, Delete Permanently');
  });
});
