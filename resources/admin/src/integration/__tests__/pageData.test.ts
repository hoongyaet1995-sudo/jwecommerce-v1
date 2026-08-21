import { describe, expect, it, vi } from 'vitest';
import {
  CmsApiError,
  archivePage,
  createPage,
  fetchPageDraft,
  fetchPagePreview,
  publishPage,
  savePageDraft,
  unpublishPage,
} from '../pageData';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  pagesUrl: '/admin/api/pages',
};

const document = {
  title: 'Canonical page',
  slug: 'canonical-page',
  description: '',
  template: 'landing' as const,
  heroImage: null,
  seoTitle: '',
  seoDescription: '',
  sections: [{
    id: 'section-1',
    type: 'hero_banner' as const,
    title: 'Hero',
    isVisible: true,
    customColors: {},
    items: [],
  }],
};

const draftPayload = {
  pageId: '42',
  status: 'published' as const,
  schemaVersion: 1,
  lockVersion: 8,
  contentHash: 'draft-hash',
  hasPublishedSnapshot: true,
  hasUnpublishedChanges: true,
  document,
  updatedAt: '2026-08-21T00:00:00.000000Z',
};

const jsonResponse = (payload: unknown, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { 'Content-Type': 'application/json' },
});

describe('canonical page draft and lifecycle API', () => {
  it('reads and saves the complete draft envelope without changing object or list tokens', async () => {
    const request = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ data: draftPayload }))
      .mockResolvedValueOnce(jsonResponse({ data: draftPayload }));

    await expect(fetchPageDraft(bootstrap, '42', request)).resolves.toEqual(draftPayload);
    await expect(savePageDraft(bootstrap, '42', document, 7, request)).resolves.toEqual(draftPayload);

    expect(request).toHaveBeenNthCalledWith(1, '/admin/api/pages/42/draft', expect.objectContaining({
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }));
    expect(request).toHaveBeenNthCalledWith(2, '/admin/api/pages/42/draft', expect.objectContaining({
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': 'csrf-token',
      },
      body: JSON.stringify({ lockVersion: 7, document }),
    }));
    expect((await savePageDraft(bootstrap, '42', document, 7, vi.fn()
      .mockResolvedValue(jsonResponse({ data: draftPayload })))).document.sections[0].customColors)
      .toEqual({});
  });

  it('creates a page with only the canonical document, never client runtime metadata', async () => {
    const page = {
      id: 'client-generated-id',
      ...document,
      status: 'draft' as const,
      lockVersion: 1,
      hasPublishedSnapshot: false,
      hasUnpublishedChanges: true,
      createdAt: '2026-08-21',
      updatedAt: '2026-08-21',
    };
    const request = vi.fn().mockResolvedValue(jsonResponse({ data: page }, 201));
    vi.stubGlobal('window', { fetch: request });

    await expect(createPage(bootstrap, page)).resolves.toEqual(page);

    expect(request).toHaveBeenCalledWith('/admin/api/pages', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify(document),
    }));
  });

  it('throws a stable typed conflict containing the current server version and no replacement document', async () => {
    const request = vi.fn().mockResolvedValue(jsonResponse({
      message: 'The page draft was changed by another session.',
      code: 'cms_version_conflict',
      errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
      currentLockVersion: 11,
    }, 409));

    const error = await savePageDraft(bootstrap, '42', document, 7, request).catch(value => value);

    expect(error).toBeInstanceOf(CmsApiError);
    expect(error).toMatchObject({
      status: 409,
      code: 'cms_version_conflict',
      currentLockVersion: 11,
      errors: { lockVersion: ['Reload or reconcile your local changes before saving again.'] },
    });
    expect(error).not.toHaveProperty('document');
  });

  it('sends lifecycle actions to their canonical endpoints with the acknowledged draft version', async () => {
    const pageSummary = {
      id: '42',
      ...document,
      status: 'published' as const,
      lockVersion: 8,
      hasPublishedSnapshot: true,
      hasUnpublishedChanges: false,
      createdAt: '2026-08-20',
      updatedAt: '2026-08-21',
    };
    const request = vi.fn().mockImplementation(async () => jsonResponse({ data: pageSummary }));

    await publishPage(bootstrap, '42', 8, request);
    await unpublishPage(bootstrap, '42', request);
    await archivePage(bootstrap, '42', request);

    expect(request).toHaveBeenNthCalledWith(1, '/admin/api/pages/42/publish', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ lockVersion: 8 }),
    }));
    expect(request).toHaveBeenNthCalledWith(2, '/admin/api/pages/42/unpublish', expect.objectContaining({
      method: 'POST',
    }));
    expect(request).toHaveBeenNthCalledWith(3, '/admin/api/pages/42/archive', expect.objectContaining({
      method: 'POST',
    }));
  });

  it('prepares the protected saved-preview GET without changing the renderer', async () => {
    const payload = { data: { page: { document }, context: { mode: 'preview' } } };
    const request = vi.fn().mockResolvedValue(jsonResponse(payload));

    await expect(fetchPagePreview(bootstrap, '42', request)).resolves.toEqual(payload.data);
    expect(request).toHaveBeenCalledWith('/admin/api/pages/42/preview', expect.objectContaining({
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }));
  });
});
