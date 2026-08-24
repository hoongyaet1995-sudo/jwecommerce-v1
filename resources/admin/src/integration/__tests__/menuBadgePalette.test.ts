import { describe, expect, it } from 'vitest';
import { MENU_BADGE_PALETTE, menuBadgeTextColor } from '../menuBadgePalette';

describe('menu badge palette', () => {
  it('provides approved palette colors and readable foreground colors', () => {
    expect(MENU_BADGE_PALETTE.map((color) => color.value)).toContain('#111827');
    expect(MENU_BADGE_PALETTE.map((color) => color.value)).toContain('#f59e0b');
    expect(menuBadgeTextColor('#111827')).toBe('#ffffff');
    expect(menuBadgeTextColor('#f59e0b')).toBe('#111827');
  });
});
