// @vitest-environment jsdom
import React, {act, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {expect, it, vi} from 'vitest';
import type {CmsPage} from '../../types';
import {PageUrlSlugField} from './PageUrlSlugField';

it('previews edits and commits only valid slugs to the page draft', async () => {
  const host = document.createElement('div');
  document.body.append(host);
  const root = createRoot(host);
  const onChange = vi.fn();
  const original: CmsPage = {
    id: '17', title: 'Marketplace', slug: 'old-marketplace', publishedSlug: 'old-marketplace',
    template: 'standard', status: 'published', sections: [], createdAt: '', updatedAt: '',
  };
  function Harness() {
    const [page, setPage] = useState(original);
    return <PageUrlSlugField page={page} onChange={slug => {
      onChange(slug);
      setPage({...page, slug});
    }}/>;
  }
  try {
    await act(async () => root.render(<Harness/>));
    const input = host.querySelector('input')!;
    const setValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
    async function type(value: string) {
      await act(async () => {
        input.focus();
        setValue.call(input, value);
        input.dispatchEvent(new Event('input', {bubbles: true}));
      });
    }
    await type('Invalid URL');
    expect(input.getAttribute('aria-invalid')).toBe('true');
    await act(async () => input.blur());
    expect(onChange).not.toHaveBeenCalled();
    await type('marketplace');
    expect(host.querySelector('[aria-live]')?.textContent).toMatch(/\/pages\/marketplace$/);
    expect(onChange).not.toHaveBeenCalled();
    await act(async () => input.blur());
    expect(onChange).toHaveBeenLastCalledWith('marketplace');
    await act(async () => host.querySelector('button')!.click());
    expect(onChange).toHaveBeenLastCalledWith('old-marketplace');
    expect(input.value).toBe('old-marketplace');
  } finally {
    await act(async () => root.unmount());
    host.remove();
  }
});
