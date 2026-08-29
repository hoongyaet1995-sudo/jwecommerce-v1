import { describe, expect, it } from 'vitest';
import { packageDraftFromParcel, packageSpecFromDraft } from './easyParcelPackageConfirmation';

describe('EasyParcel package confirmation', () => {
  it('prefills the order snapshot without inventing missing dimensions', () => {
    const draft = packageDraftFromParcel({
      totalWeightKg: 1.25,
      dimensionsCm: null,
    });

    expect(draft).toEqual({
      weightKg: '1.25',
      lengthCm: '',
      widthCm: '',
      heightCm: '',
    });
  });

  it('returns the exact validated two-decimal package sent for quotation', () => {
    expect(packageSpecFromDraft({
      weightKg: '1.25',
      lengthCm: '28',
      widthCm: '18',
      heightCm: '8',
    })).toEqual({ weightKg: 1.25, lengthCm: 28, widthCm: 18, heightCm: 8 });
  });

  it('normalizes three-decimal snapshot prefills to the accepted two-decimal precision', () => {
    expect(packageDraftFromParcel({
      totalWeightKg: 1.234,
      dimensionsCm: { length: 28.346, width: 18.111, height: 8.999 },
    })).toEqual({
      weightKg: '1.23',
      lengthCm: '28.35',
      widthCm: '18.11',
      heightCm: '9',
    });
  });

  it.each(['', '0', '-1', '1.234', 'abc'])('rejects invalid package values: %s', (value) => {
    expect(() => packageSpecFromDraft({
      weightKg: value,
      lengthCm: '28',
      widthCm: '18',
      heightCm: '8',
    })).toThrow('Enter weight and dimensions as positive numbers with up to 2 decimals.');
  });
});
