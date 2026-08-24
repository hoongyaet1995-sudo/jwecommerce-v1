import { MENU_BADGE_PALETTE, menuBadgeTextColor, type MenuBadgeColor } from '../../integration/menuBadgePalette';

interface MenuBadgeEditorProps {
  label?: string;
  badge?: string;
  badgeColor?: string;
  onChange: (next: { badge?: string; badgeColor?: MenuBadgeColor }) => void;
}

export function MenuBadgeEditor({ label = 'Badge (Optional)', badge, badgeColor, onChange }: MenuBadgeEditorProps) {
  const selectedColor = MENU_BADGE_PALETTE.find((color) => color.value === badgeColor)?.value;

  return (
    <div>
      <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">{label}</label>
      <input
        type="text"
        placeholder="e.g. HOT, NEW"
        value={badge || ''}
        onChange={(event) => onChange({ badge: event.target.value || undefined, badgeColor: selectedColor })}
        className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
      />
      <div className="mt-2 flex flex-wrap items-center gap-1.5" aria-label="Badge color palette">
        {MENU_BADGE_PALETTE.map((color) => {
          const active = selectedColor === color.value;
          return (
            <button
              key={color.value}
              type="button"
              title={color.label}
              aria-label={`Use ${color.label} badge color`}
              aria-pressed={active}
              onClick={() => onChange({ badge, badgeColor: color.value })}
              className={`grid h-5 w-5 place-items-center rounded-full border transition-transform hover:scale-110 ${active ? 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-white dark:ring-offset-neutral-900' : 'border-neutral-400/70'}`}
              style={{ backgroundColor: color.value, color: menuBadgeTextColor(color.value) }}
            >
              {active ? <span className="text-[9px] font-black">✓</span> : null}
            </button>
          );
        })}
      </div>
    </div>
  );
}
