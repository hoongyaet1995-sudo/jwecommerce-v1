import type { Product } from './types';

export const primaryProductImage = (product: Pick<Product, 'images'>): string | undefined => {
  const image = product.images[0];

  return typeof image === 'string' ? image : image?.path;
};
