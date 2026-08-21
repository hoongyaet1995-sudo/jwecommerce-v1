import { describe, expect, it, vi } from 'vitest';
import * as pageData from '../pageData';

const bootstrap = {
  user: { name: 'Julian Admin', email: 'julian@example.test', avatar: null },
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  pagesUrl: '/admin/api/pages',
  mediaAssetsUrl: '/admin/api/media-assets',
};

describe('managed media upload', () => {
  it('posts one multipart request from the file event and returns the managed URL', async () => {
    const upload = (pageData as Record<string, unknown>).uploadMediaAsset;
    expect(typeof upload).toBe('function');
    if (typeof upload !== 'function') return;

    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      data: {
        id: '7ea92b56-24d3-4f35-b80c-44cf0bd78292',
        url: '/storage/cms/media/7ea92b56-24d3-4f35-b80c-44cf0bd78292.webp',
        mimeType: 'image/webp',
        size: 128,
      },
    }), { status: 201, headers: { 'Content-Type': 'application/json' } }));
    const file = new File(['managed-bytes'], 'hero.webp', { type: 'image/webp' });

    const url = await (upload as Function)(bootstrap, file, request);

    expect(url).toBe('/storage/cms/media/7ea92b56-24d3-4f35-b80c-44cf0bd78292.webp');
    expect(request).toHaveBeenCalledTimes(1);
    const [path, init] = request.mock.calls[0];
    expect(path).toBe('/admin/api/media-assets');
    expect(init).toEqual(expect.objectContaining({
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },
    }));
    expect(init.body).toBeInstanceOf(FormData);
    expect((init.body as FormData).get('file')).toBe(file);
  });

  it('surfaces the stable server validation detail', async () => {
    const upload = (pageData as Record<string, unknown>).uploadMediaAsset;
    expect(typeof upload).toBe('function');
    if (typeof upload !== 'function') return;

    const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({
      message: 'The media asset upload is invalid.',
      code: 'cms_media_upload_invalid',
      errors: { file: ['The selected file type is not allowed.'] },
    }), { status: 422, headers: { 'Content-Type': 'application/json' } }));

    await expect((upload as Function)(
      bootstrap,
      new File(['unsafe'], 'unsafe.svg', { type: 'image/svg+xml' }),
      request,
    )).rejects.toThrow('The selected file type is not allowed.');
    expect(request).toHaveBeenCalledTimes(1);
  });
});
