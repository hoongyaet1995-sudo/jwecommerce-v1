export const MENU_BADGE_PALETTE = [
  { value: '#111827', label: 'Charcoal' },
  { value: '#4338ca', label: 'Indigo' },
  { value: '#0f766e', label: 'Teal' },
  { value: '#be123c', label: 'Rose' },
  { value: '#f59e0b', label: 'Amber' },
  { value: '#f97316', label: 'Orange' },
  { value: '#06b6d4', label: 'Cyan' },
  { value: '#e5e7eb', label: 'Mist' },
] as const;

export type MenuBadgeColor = typeof MENU_BADGE_PALETTE[number]['value'];

export function menuBadgeTextColor(background: MenuBadgeColor): '#ffffff' | '#111827' {
  const channels = [
    Number.parseInt(background.slice(1, 3), 16),
    Number.parseInt(background.slice(3, 5), 16),
    Number.parseInt(background.slice(5, 7), 16),
  ];
  const linear = channels.map((channel) => {
    const normalized = channel / 255;
    return normalized <= 0.03928 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
  });
  const luminance = 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
  return (1.05 / (luminance + 0.05)) >= ((luminance + 0.05) / 0.05) ? '#ffffff' : '#111827';
}
