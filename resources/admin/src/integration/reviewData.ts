import type {
  AdminProductReview,
  AdminProductReviewAuditEvent,
  LaravelAdminBootstrap,
  ReviewBulkModerationItemResult,
  ReviewBulkModerationResult,
  ReviewModerationAction,
  ReviewModerationResult,
  ReviewModerationStatus,
} from '../types';

type ErrorPayload = { message?: string; errors?: Record<string, string[]> };
type RequestPayload<T> = { data?: T; message?: string; errors?: Record<string, string[]> } | null;

type ReviewRecord = {
  id: number | string;
  productId: number | string;
  productTitle: string;
  productThumbnail?: string | null;
  productHandle?: string | null;
  customerId: number | string;
  buyerName: string;
  customerEmail: string;
  orderNumber: string;
  variantTitle?: string | null;
  rating: number;
  title: string;
  comment: string;
  status: ReviewModerationStatus;
  moderationNote?: string | null;
  moderatedBy?: string | null;
  submittedAt: string;
  publishedAt?: string | null;
  photos: Array<{ id: number | string; url: string; mimeType: string }>;
};

type ReviewAuditRecord = {
  id: number | string;
  eventType: string;
  fromStatus: ReviewModerationStatus | null;
  toStatus: ReviewModerationStatus | null;
  batchId: string | null;
  metadata?: Record<string, unknown> | null;
  actorName?: string | null;
  occurredAt?: string | null;
};

type ReviewModerationResponse = { id: number | string; status: ReviewModerationStatus };
type ReviewBulkModerationResponse = {
  results: Array<{
    reviewId: number | string;
    ok: boolean;
    status?: ReviewModerationStatus;
    reason?: string;
  }>;
};

const errorMessage = (payload: ErrorPayload | null, fallback: string): string =>
  payload?.errors ? Object.values(payload.errors).flat().join(' ') : payload?.message ?? fallback;

const request = async <T>(
  bootstrap: LaravelAdminBootstrap,
  url: string,
  method: 'GET' | 'POST',
  body?: unknown,
): Promise<T | null> => {
  const response = await window.fetch(url, {
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

  const payload = await response.json().catch(() => null) as RequestPayload<T>;

  if (!response.ok) {
    throw new Error(errorMessage(payload, 'Review request failed.'));
  }

  return (payload?.data ?? payload) as T;
};

const mapReview = (review: ReviewRecord): AdminProductReview => ({
  id: String(review.id),
  productId: String(review.productId),
  productTitle: review.productTitle,
  productThumbnail: review.productThumbnail ?? null,
  productHandle: review.productHandle ?? null,
  customerId: String(review.customerId),
  buyerName: review.buyerName,
  customerEmail: review.customerEmail,
  orderNumber: review.orderNumber,
  variantTitle: review.variantTitle ?? null,
  rating: review.rating,
  title: review.title,
  comment: review.comment,
  status: review.status,
  moderationNote: review.moderationNote ?? null,
  moderatedBy: review.moderatedBy ?? null,
  submittedAt: review.submittedAt,
  publishedAt: review.publishedAt ?? null,
  photos: Array.isArray(review.photos)
    ? review.photos.map((photo) => ({
        id: String(photo.id),
        url: photo.url,
        mimeType: photo.mimeType,
      }))
    : [],
});

const mapAuditEvent = (event: ReviewAuditRecord): AdminProductReviewAuditEvent => ({
  id: String(event.id),
  eventType: event.eventType,
  fromStatus: event.fromStatus,
  toStatus: event.toStatus,
  batchId: event.batchId,
  metadata: event.metadata ?? {},
  actorName: event.actorName ?? null,
  occurredAt: event.occurredAt ?? null,
});

const mapModerationResult = (result: ReviewModerationResponse): ReviewModerationResult => ({
  id: String(result.id),
  status: result.status,
});

const mapBulkModerationItem = (result: ReviewBulkModerationResponse['results'][number]): ReviewBulkModerationItemResult => ({
  reviewId: String(result.reviewId),
  ok: result.ok,
  ...(result.status === undefined ? {} : { status: result.status }),
  ...(result.reason === undefined ? {} : { reason: result.reason }),
});

export const fetchReviews = async (bootstrap: LaravelAdminBootstrap): Promise<AdminProductReview[]> => {
  if (!bootstrap.reviewsUrl) throw new Error('Review API is unavailable.');
  const data = await request<ReviewRecord[]>(bootstrap, bootstrap.reviewsUrl, 'GET');
  if (!Array.isArray(data)) throw new Error('Review API returned an invalid document.');
  return data.map(mapReview);
};

export const fetchReviewAudit = async (
  bootstrap: LaravelAdminBootstrap,
  reviewId: string,
): Promise<AdminProductReviewAuditEvent[]> => {
  if (!bootstrap.reviewsUrl) throw new Error('Review API is unavailable.');
  const data = await request<ReviewAuditRecord[]>(
    bootstrap,
    `${bootstrap.reviewsUrl}/${encodeURIComponent(reviewId)}/audit`,
    'GET',
  );
  if (!Array.isArray(data)) throw new Error('Review audit API returned an invalid document.');
  return data.map(mapAuditEvent);
};

export async function moderateReview(
  bootstrap: LaravelAdminBootstrap,
  reviewId: string,
  action: ReviewModerationAction,
  reason?: string,
): Promise<ReviewModerationResult> {
  if (!bootstrap.reviewsUrl) throw new Error('Review API is unavailable.');
  const result = await request<ReviewModerationResponse>(
    bootstrap,
    `${bootstrap.reviewsUrl}/${encodeURIComponent(reviewId)}/moderate`,
    'POST',
    { action, reason },
  );
  if (!result) throw new Error('Review moderation response was empty.');
  return mapModerationResult(result);
}

export async function bulkModerateReviews(
  bootstrap: LaravelAdminBootstrap,
  reviewIds: string[],
  action: ReviewModerationAction,
  reason?: string,
): Promise<ReviewBulkModerationResult> {
  if (!bootstrap.reviewBulkModerateUrl) throw new Error('Review bulk moderation API is unavailable.');
  const result = await request<ReviewBulkModerationResponse>(
    bootstrap,
    bootstrap.reviewBulkModerateUrl,
    'POST',
    { reviewIds, action, reason },
  );
  if (!result || !Array.isArray(result.results)) throw new Error('Review bulk moderation response was invalid.');
  return {
    results: result.results.map(mapBulkModerationItem),
  };
}
