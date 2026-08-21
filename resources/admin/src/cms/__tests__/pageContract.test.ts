import { describe, expect, it } from 'vitest';
import { hasOwnContractField } from '../pageContract';

describe('page contract field presence', () => {
  it('distinguishes a missing field from a present empty field', () => {
    expect(hasOwnContractField({ subtitle: '' }, 'subtitle')).toBe(true);
    expect(hasOwnContractField({}, 'subtitle')).toBe(false);
  });

  it('preserves false and zero as present values', () => {
    expect(hasOwnContractField({ sliderAutoplay: false }, 'sliderAutoplay')).toBe(true);
    expect(hasOwnContractField({ overlayOpacity: 0 }, 'overlayOpacity')).toBe(true);
  });
});
