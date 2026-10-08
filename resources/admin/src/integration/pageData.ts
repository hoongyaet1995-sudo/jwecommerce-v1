import type { CmsPage, LaravelAdminBootstrap, PageDocument, PageDraftPayload } from '../types';

type Fetcher = (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>;

interface PageResponse {
  data: CmsPage | CmsPage[];
}

interface PageDraftResponse {
  data: PageDraftPayload;
}

interface PagePreviewResponse {
  data: unknown;
}

interface MediaAssetResponse {
  data: {
    id: string;
    url: string;
    mimeType: string;
    size: number;
  };
}

interface ErrorResponse {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
  currentLockVersion?: number;
}

export class CmsApiError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly errors: Record<string, string[]>;
  readonly currentLockVersion: number | null;

  constructor(status: number, payload: ErrorResponse | null, detail: string) {
    super(detail);
    this.name = 'CmsApiError';
    this.status = status;
    this.code = payload?.code ?? null;
    this.errors = payload?.errors ?? {};
    this.currentLockVersion = payload?.currentLockVersion ?? null;
  }
}

const request = async <T>(
  bootstrap: LaravelAdminBootstrap,
  path: string,
  method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
  body?: unknown,
  fetcher: Fetcher = window.fetch.bind(window),
): Promise<T | null> => {
  if (!bootstrap.pagesUrl) throw new Error('CMS API is unavailable.');

  const response = await fetcher(path, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(method === 'GET' ? {} : { 'X-CSRF-TOKEN': bootstrap.csrfToken }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });

  if (response.status === 204) return null;

  const payload = await response.json().catch(() => null) as ErrorResponse | T | null;

  if (!response.ok) {
    const errorPayload = payload as ErrorResponse | null;
    const validationDetail = Object.values(errorPayload?.errors ?? {}).flat().join(' ');
    const detail = validationDetail || errorPayload?.message || 'CMS request failed.';
    throw new CmsApiError(response.status, errorPayload, detail);
  }

  return payload as T;
};

export const fetchPages = async (bootstrap: LaravelAdminBootstrap): Promise<CmsPage[]> => {
  const response = await request<PageResponse>(bootstrap, bootstrap.pagesUrl ?? '', 'GET');
  return response && Array.isArray(response.data) ? response.data : [];
};

const pagePath = (bootstrap: LaravelAdminBootstrap, id: string, suffix = ''): string => {
  if (!bootstrap.pagesUrl) throw new Error('CMS API is unavailable.');
  return `${bootstrap.pagesUrl}/${encodeURIComponent(id)}${suffix}`;
};

export const selectHomepage = async (bootstrap: LaravelAdminBootstrap, id: string, lockVersion: number): Promise<CmsPage> => {
  const result = await request<{ data: CmsPage }>(bootstrap, pagePath(bootstrap, id, '/homepage'), 'POST', { lockVersion });
  if (!result) throw new Error('Homepage switch failed.');
  return result.data;
};

export const fetchPageDraft = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  fetcher?: Fetcher,
): Promise<PageDraftPayload> => {
  const response = await request<PageDraftResponse>(
    bootstrap,
    pagePath(bootstrap, id, '/draft'),
    'GET',
    undefined,
    fetcher,
  );
  if (!response) throw new Error('CMS draft response was invalid.');
  return response.data;
};

export const savePageDraft = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  document: PageDocument,
  lockVersion: number,
  fetcher?: Fetcher,
): Promise<PageDraftPayload> => {
  const response = await request<PageDraftResponse>(
    bootstrap,
    pagePath(bootstrap, id, '/draft'),
    'PATCH',
    { lockVersion, document },
    fetcher,
  );
  if (!response) throw new Error('CMS save response was invalid.');
  return response.data;
};

const lifecycleRequest = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  action: 'publish' | 'unpublish' | 'archive',
  body: unknown,
  fetcher?: Fetcher,
): Promise<CmsPage> => {
  const response = await request<PageResponse>(
    bootstrap,
    pagePath(bootstrap, id, `/${action}`),
    'POST',
    body,
    fetcher,
  );
  if (!response || Array.isArray(response.data)) throw new Error('CMS lifecycle response was invalid.');
  return response.data;
};

export const publishPage = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  lockVersion: number,
  fetcher?: Fetcher,
): Promise<CmsPage> => lifecycleRequest(bootstrap, id, 'publish', { lockVersion }, fetcher);

export const unpublishPage = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  fetcher?: Fetcher,
): Promise<CmsPage> => lifecycleRequest(bootstrap, id, 'unpublish', undefined, fetcher);

export const archivePage = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  fetcher?: Fetcher,
): Promise<CmsPage> => lifecycleRequest(bootstrap, id, 'archive', undefined, fetcher);

export const fetchPagePreview = async (
  bootstrap: LaravelAdminBootstrap,
  id: string,
  fetcher?: Fetcher,
): Promise<unknown> => {
  const response = await request<PagePreviewResponse>(
    bootstrap,
    pagePath(bootstrap, id, '/preview'),
    'GET',
    undefined,
    fetcher,
  );
  if (!response) throw new Error('CMS preview response was invalid.');
  return response.data;
};

export const createPage = async (bootstrap: LaravelAdminBootstrap, page: CmsPage): Promise<CmsPage> => {
  const document: PageDocument = {
    title: page.title,
    slug: page.slug,
    description: page.description,
    template: page.template,
    ...(page.containerWidth?{containerWidth:page.containerWidth}:{}),
    heroImage: page.heroImage,
    seoTitle: page.seoTitle,
    seoDescription: page.seoDescription,
    sections: page.sections,
  };
  const response = await request<PageResponse>(bootstrap, bootstrap.pagesUrl ?? '', 'POST', document);
  if (!response || Array.isArray(response.data)) throw new Error('CMS create response was invalid.');
  return response.data;
};

export const updatePage = async (bootstrap: LaravelAdminBootstrap, page: CmsPage): Promise<CmsPage> => {
  const response = await request<PageResponse>(bootstrap, `${bootstrap.pagesUrl}/${encodeURIComponent(page.id)}`, 'PATCH', page);
  if (!response || Array.isArray(response.data)) throw new Error('CMS save response was invalid.');
  return response.data;
};

export const removePage = async (bootstrap: LaravelAdminBootstrap, id: string): Promise<void> => {
  await request<PageResponse>(bootstrap, `${bootstrap.pagesUrl}/${encodeURIComponent(id)}`, 'DELETE');
};

export const clonePage = async (bootstrap: LaravelAdminBootstrap, id: string): Promise<CmsPage> => {
  const response = await request<PageResponse>(bootstrap, `${bootstrap.pagesUrl}/${encodeURIComponent(id)}/duplicate`, 'POST');
  if (!response || Array.isArray(response.data)) throw new Error('CMS duplicate response was invalid.');
  return response.data;
};

export const uploadMediaAsset = async (
  bootstrap: LaravelAdminBootstrap,
  file: File,
  fetcher: Fetcher = window.fetch.bind(window),
): Promise<string> => {
  if (!bootstrap.mediaAssetsUrl) throw new Error('Managed media upload is unavailable.');

  const form = new FormData();
  form.append('file', file);
  const response = await fetcher(bootstrap.mediaAssetsUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': bootstrap.csrfToken,
    },
    body: form,
  });
  const payload = await response.json().catch(() => null) as
    | MediaAssetResponse
    | { message?: string; errors?: Record<string, string[]> }
    | null;

  if (!response.ok) {
    const detail = payload && 'errors' in payload
      ? Object.values(payload.errors ?? {}).flat().join(' ')
      : payload && 'message' in payload ? payload.message : 'Managed media upload failed.';
    throw new Error(detail);
  }

  if (!payload || !('data' in payload) || typeof payload.data?.url !== 'string') {
    throw new Error('Managed media upload response was invalid.');
  }

  return payload.data.url;
};
