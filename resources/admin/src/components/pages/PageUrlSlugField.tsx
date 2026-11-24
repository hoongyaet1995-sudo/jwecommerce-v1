import {useId, useState} from 'react';
import type {CmsPage} from '../../types';

export function PageUrlSlugField({page, onChange}: {page: CmsPage; onChange: (slug: string) => void}) {
  const id = useId();
  const [input, setInput] = useState<string | null>(null);
  const value = input ?? page.slug;
  const baseline = page.publishedSlug ?? page.slug;
  const valid = value === page.slug || (value.length <= 255 && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value));
  const path = page.isHomepage ? '/' : '/pages/' + encodeURIComponent(value);
  const storefrontUrl = (typeof window === 'undefined' ? '' : window.location.origin) + path;

  const commit = () => {
    if (!valid) return;
    if (value !== page.slug) onChange(value);
    setInput(null);
  };

  return <div className="space-y-3 pb-4">
    <div>
      <label htmlFor={id} className="block text-xs font-semibold mb-1.5">URL Slug</label>
      <div className="flex items-center overflow-hidden rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-950 focus-within:ring-2 focus-within:ring-indigo-500">
        <span aria-hidden="true" className="border-r border-neutral-300 dark:border-neutral-700 px-3 py-2 text-sm text-neutral-500">/pages/</span>
        <input id={id} className="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none" value={value}
          onChange={event => setInput(event.target.value)} onBlur={commit}
          onKeyDown={event => { if (event.key === 'Enter') event.currentTarget.blur(); }}
          aria-describedby={id + '-hint'} aria-invalid={!valid} spellCheck={false} autoCapitalize="none" autoComplete="off"/>
      </div>
    </div>
    <div className="rounded-lg bg-neutral-100 dark:bg-neutral-800 p-3 text-xs">
      <p className="text-neutral-500 dark:text-neutral-400">Storefront URL</p>
      <p className="mt-1 break-all" aria-live="polite">{valid ? storefrontUrl : '—'}</p>
    </div>
    <p id={id + '-hint'} className={'text-xs ' + (valid ? 'text-neutral-500 dark:text-neutral-400' : 'text-red-600 dark:text-red-300')}>
      {!valid ? 'Use up to 255 lowercase letters, numbers and single hyphens between words.'
        : page.isHomepage ? 'The active homepage uses /. The slug is used if this page is no longer the homepage.'
        : page.publishedSlug && value !== page.publishedSlug ? 'The URL updates when you publish. The old link redirects to the new address.'
        : 'Edit the last part of the page address.'}
    </p>
    <div className="flex justify-end">
      <button type="button" className="rounded-lg border border-neutral-300 dark:border-neutral-700 px-3 py-1.5 text-xs disabled:opacity-50"
        disabled={value === baseline && page.slug === baseline}
        onClick={() => { setInput(null); if (page.slug !== baseline) onChange(baseline); }}>Reset</button>
    </div>
  </div>;
}
