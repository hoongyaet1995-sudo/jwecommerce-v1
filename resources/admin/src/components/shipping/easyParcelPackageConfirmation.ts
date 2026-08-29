import type { EasyParcelPackageSpec } from '../../integration/easyParcelFulfillmentData';
import type { ShipmentParcel } from '../../types/shipping';

export interface EasyParcelPackageDraft {
  weightKg: string;
  lengthCm: string;
  widthCm: string;
  heightCm: string;
}

type PackageSource = Pick<ShipmentParcel, 'totalWeightKg' | 'dimensionsCm'>;

export const packageDraftFromParcel = (parcel: PackageSource): EasyParcelPackageDraft => ({
  weightKg: parcel.totalWeightKg?.toString() ?? '',
  lengthCm: parcel.dimensionsCm?.length.toString() ?? '',
  widthCm: parcel.dimensionsCm?.width.toString() ?? '',
  heightCm: parcel.dimensionsCm?.height.toString() ?? '',
});

export const packageSpecFromDraft = (draft: EasyParcelPackageDraft): EasyParcelPackageSpec => {
  const pattern = /^\d{1,6}(?:\.\d{1,2})?$/;
  const values = Object.values(draft);
  if (values.some((value) => !pattern.test(value) || Number(value) <= 0 || Number(value) > 999999.99)) {
    throw new Error('Enter weight and dimensions as positive numbers with up to 2 decimals.');
  }

  return {
    weightKg: Number(draft.weightKg),
    lengthCm: Number(draft.lengthCm),
    widthCm: Number(draft.widthCm),
    heightCm: Number(draft.heightCm),
  };
};
