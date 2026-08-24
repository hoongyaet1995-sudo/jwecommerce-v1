import type { ProductMedia } from '../../types';

export interface ProductMediaDeletionDescription {
  itemName: string;
  itemDetails: string;
  warningMessage: string;
  impactItems: string[];
}

export const describeProductMediaDeletion = (
  images: ProductMedia[],
  indexes: number[],
): ProductMediaDeletionDescription => {
  const count = indexes.length;
  const singular = count === 1;
  const selectedImage = singular ? images[indexes[0]] : undefined;
  const countLabel = `${count} selected product image${singular ? '' : 's'}`;

  return {
    itemName: singular ? selectedImage?.alt || 'Product image' : countLabel,
    itemDetails: countLabel,
    warningMessage: 'This removes the selected image from the current product edit. The removal is saved only when you choose Save Product & SKUs.',
    impactItems: [
      `The selected image${singular ? '' : 's'} will no longer be available on this product after the product is saved.`,
      'The first remaining image continues to be the storefront and cart thumbnail.',
    ],
  };
};
