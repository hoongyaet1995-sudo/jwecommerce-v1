import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LaravelAdminBootstrap } from '../types';
import {
  bulkModerateReviews,
  fetchReviewAudit,
  fetchReviews,
  moderateReview,
} from './reviewData';

const bootstrap: LaravelAdminBootstrap = {
  user: {
    id: '7',
    name: 'Julian Admin',
    email: 'julian@example.test',
    avatar: null,
    isSuperAdmin: false,
    reviewPermissions: ['reviews.view', 'reviews.moderate', 'reviews.bulk_moderate', 'reviews.audit.view'],
  },
  reviewPermissions: ['reviews.view', 'reviews.moderate', 'reviews.bulk_moderate', 'reviews.audit.view'],
  csrfToken: 'csrf-token',
  logoutUrl: '/logout',
  reviewsUrl: '/admin/api/reviews',
  reviewBulkModerateUrl: '/admin/api/reviews/bulk-moderate',
  staffReviewPermissionsUrl: '/admin/api/staff/review-permissions',
};

const jsonResponse = (payload: unknown, status = 200) => new Response(JSON.stringify(payload), {
  status,
  headers: { 'Content-Type': 'application/json' },
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('Laravel review data adapter', () => {
  it('maps the persisted review list with private photo payloads and strict GET headers', async () => {
    const request = vi.fn().mockResolvedValue(jsonResponse({
      data: [{
        id: 17,
        productId: 8,
        productTitle: 'Canvas Weekender',
        productThumbnail: '/storage/p.jpg',
        productHandle: 'canvas-weekender',
        customerId: 23,
        buyerName: 'Buyer Example',
        customerEmail: 'buyer@example.test',
        orderNumber: 'JW-00017',
        variantTitle: 'Tan / Large',
        rating: 5,
        title: 'Arrived fast',
        comment: 'Very happy with it.',
        status: 'pending',
        moderationNote: null,
        moderatedBy: null,
        submittedAt: '2026-08-24T00:00:00Z',
        publishedAt: null,
        photos: [{ id: 91, url: '/admin/api/reviews/media/91', mimeType: 'image/jpeg' }],
      }],
    }));
    vi.stubGlobal('window', { fetch: request });

    await expect(fetchReviews(bootstrap)).resolves.toEqual([expect.objectContaining({
      id: '17',
      productId: '8',
      productThumbnail: '/storage/p.jpg',
      customerEmail: 'buyer@example.test',
      status: 'pending',
      photos: [{ id: '91', url: '/admin/api/reviews/media/91', mimeType: 'image/jpeg' }],
    })]);
    expect(request).toHaveBeenCalledWith(bootstrap.reviewsUrl, expect.objectContaining({
      credentials: 'same-origin',
      headers: expect.objectContaining({ Accept: 'application/json' }),
    }));
  });

  it('sends moderation writes with CSRF, JSON body, and Laravel validation failures', async () => {
    const request = vi.fn()
      .mockResolvedValueOnce(jsonResponse({ data: { id: 17, status: 'published' } }))
      .mockResolvedValueOnce(jsonResponse({
        message: 'The given data was invalid.',
        errors: { action: ['This review cannot make the requested transition.'] },
      }, 422));
    vi.stubGlobal('window', { fetch: request });

    await expect(moderateReview(bootstrap, '17', 'publish')).resolves.toEqual({ id: '17', status: 'published' });
    await expect(moderateReview(bootstrap, '17', 'reject', 'Duplicate')).rejects.toThrow(
      'This review cannot make the requested transition.',
    );

    expect(request).toHaveBeenNthCalledWith(1, '/admin/api/reviews/17/moderate', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': 'csrf-token',
      },
      body: JSON.stringify({ action: 'publish', reason: undefined }),
    });
    expect(request).toHaveBeenNthCalledWith(2, '/admin/api/reviews/17/moderate', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': 'csrf-token',
      },
      body: JSON.stringify({ action: 'reject', reason: 'Duplicate' }),
    });
  });

  it('reads audit history and posts bulk moderation to the protected endpoint', async () => {
    const request = vi.fn()
      .mockResolvedValueOnce(jsonResponse({
        data: [{
          id: 301,
          eventType: 'submitted',
          fromStatus: null,
          toStatus: 'pending',
          batchId: null,
          metadata: { attemptNumber: 1 },
          actorName: 'Buyer Example',
          occurredAt: '2026-08-24T00:00:00Z',
        }],
      }))
      .mockResolvedValueOnce(jsonResponse({
        data: {
          results: [{ reviewId: 17, ok: true, status: 'published' }],
        },
      }));
    vi.stubGlobal('window', { fetch: request });

    await expect(fetchReviewAudit(bootstrap, '17')).resolves.toEqual([expect.objectContaining({
      id: '301',
      eventType: 'submitted',
      toStatus: 'pending',
    })]);
    await expect(bulkModerateReviews(bootstrap, ['17'], 'publish')).resolves.toEqual({
      results: [{ reviewId: '17', ok: true, status: 'published' }],
    });

    expect(request).toHaveBeenNthCalledWith(1, '/admin/api/reviews/17/audit', expect.objectContaining({
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    }));
    expect(request).toHaveBeenNthCalledWith(2, bootstrap.reviewBulkModerateUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': 'csrf-token',
      },
      body: JSON.stringify({ reviewIds: ['17'], action: 'publish', reason: undefined }),
    });
  });
});
