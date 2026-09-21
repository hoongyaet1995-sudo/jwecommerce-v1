import type { Product, ProductVariant } from '../../types';

export const isNewCatalogVariant = (variant: ProductVariant): boolean => !/^\d+$/.test(variant.id);

export const canEditCatalogStock = (variant: ProductVariant): boolean =>
  isNewCatalogVariant(variant) || variant.inventoryEditable === true;

export const applyEditableInventoryQuantity = (variants: ProductVariant[], quantity: number): ProductVariant[] =>
  variants.map(variant => canEditCatalogStock(variant) ? { ...variant, inventoryQuantity: quantity } : variant);

export const catalogStockPayload = (current: Product, initial: Product | null): Product => {
  const initialById = new Map((initial?.variants || []).map(variant => [variant.id, variant]));
  const variants = current.variants.map(variant => {
    const baseline = initialById.get(variant.id);
    const includeQuantity = isNewCatalogVariant(variant)
      || (variant.inventoryEditable === true && baseline !== undefined && baseline.inventoryQuantity !== variant.inventoryQuantity);
    if (includeQuantity) return { ...variant };

    const payload = { ...variant } as ProductVariant & { inventoryQuantity?: number; inventoryRevision?: string };
    delete payload.inventoryQuantity;
    delete payload.inventoryRevision;
    return payload as ProductVariant;
  });

  return { ...current, variants };
};
