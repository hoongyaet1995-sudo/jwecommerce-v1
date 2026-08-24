import type { Category, CmsPage, Collection, MenuLinkType } from '../../types';
import { buildMenuTargetOptions, isInternalMenuLinkType } from '../../integration/menuLinkTargets';

interface MenuLinkTargetPickerProps {
  linkType: MenuLinkType;
  targetId?: string;
  url: string;
  categories: Category[];
  collections: Collection[];
  pages: CmsPage[];
  onTargetChange: (targetId: string | undefined, url: string) => void;
  manualLabel?: string;
  manualPlaceholder?: string;
}

export const MenuLinkTargetPicker = ({
  linkType,
  targetId,
  url,
  categories,
  collections,
  pages,
  onTargetChange,
  manualLabel = 'URL Path / Target',
  manualPlaceholder = '/orders/track or https://...',
}: MenuLinkTargetPickerProps) => {
  const targets = buildMenuTargetOptions(linkType, categories, collections, pages);

  if (!isInternalMenuLinkType(linkType)) {
    return (
      <div>
        <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">{manualLabel}</label>
        <input
          type="text"
          required
          placeholder={manualPlaceholder}
          value={url}
          onChange={(event) => onTargetChange(undefined, event.target.value)}
          className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white font-mono"
        />
      </div>
    );
  }

  const label = linkType === 'page' ? 'Store Page' : linkType === 'category' ? 'Category' : 'Collection';

  return (
    <div>
      <label className="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1">{label} Target</label>
      <select
        required
        value={targetId ?? ''}
        onChange={(event) => {
          const target = targets.find(candidate => candidate.id === event.target.value);
          onTargetChange(target?.id, target?.url ?? '');
        }}
        className="w-full px-3 py-2 text-xs rounded-xl border bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white"
      >
        <option value="">Select a {label.toLowerCase()}…</option>
        {targets.map(target => <option key={target.id} value={target.id}>{target.label} — {target.url}</option>)}
      </select>
      {targetId && !targets.some(target => target.id === targetId) && (
        <p className="mt-1 text-[11px] text-amber-600 dark:text-amber-400">The saved target is no longer storefront-eligible. Select a replacement before saving.</p>
      )}
    </div>
  );
};
