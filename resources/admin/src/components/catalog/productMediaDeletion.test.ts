import { describe, expect, it } from 'vitest';
import { describeProductMediaDeletion } from './productMediaDeletion';

describe('describeProductMediaDeletion', () => {
  it('explains that an individual media removal is staged until the product save', () => {
    expect(describeProductMediaDeletion([
      { path: '/storage/cms/media/hero.jpg', alt: 'Headphones hero' },
      { path: '/storage/cms/media/detail.jpg', alt: 'Headphones detail' },
    ], [1])).toEqual({
      itemName: 'Headphones detail',
      itemDetails: '1 selected product image',
      warningMessage: 'This removes the selected image from the current product edit. The removal is saved only when you choose Save Product & SKUs.',
      impactItems: [
        'The selected image will no longer be available on this product after the product is saved.',
        'The first remaining image continues to be the storefront and cart thumbnail.',
      ],
    });
  });

  it('uses factual plural copy for a batch deletion', () => {
    expect(describeProductMediaDeletion([
      { path: '/storage/cms/media/hero.jpg', alt: 'Headphones hero' },
      { path: '/storage/cms/media/detail.jpg', alt: 'Headphones detail' },
      { path: '/storage/cms/media/side.jpg', alt: 'Headphones side' },
    ], [0, 2])).toMatchObject({
      itemName: '2 selected product images',
      itemDetails: '2 selected product images',
    });
  });
});
