<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reviews\ProductReviewModerationService;
use App\Domain\Reviews\ReviewPermission;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(ReviewPermission::allows($request->user(), ReviewPermission::VIEW), 403);
        $reviews = ProductReview::query()
            ->with(['media', 'events.actor', 'product.images', 'customer.user', 'moderator'])
            ->latest('submitted_at')
            ->get();

        return response()->json(['data' => $reviews->map(fn (ProductReview $review): array => $this->reviewPayload($review))->values()]);
    }

    public function audit(Request $request, ProductReview $review): JsonResponse
    {
        abort_unless($request->user()->can('viewAudit', $review), 403);
        $events = $review->events()->with('actor')->get()->map(fn ($event): array => [
            'id' => $event->id,
            'eventType' => $event->event_type,
            'fromStatus' => $event->from_status,
            'toStatus' => $event->to_status,
            'batchId' => $event->batch_id,
            'metadata' => $event->metadata ?? [],
            'actorName' => $event->actor?->name,
            'occurredAt' => $event->occurred_at?->toIso8601String(),
        ])->values();

        return response()->json(['data' => $events]);
    }
    public function moderate(Request $request, ProductReview $review, ProductReviewModerationService $moderation): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:publish,reject'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $updated = $moderation->moderate($request->user(), $review, $data['action'], $data['reason'] ?? null);

        return response()->json(['data' => ['id' => $updated->id, 'status' => $updated->status]]);
    }

    public function bulkModerate(Request $request, ProductReviewModerationService $moderation): JsonResponse
    {
        $data = $request->validate(['reviewIds' => ['required', 'array', 'min:1', 'max:100'], 'reviewIds.*' => ['integer'], 'action' => ['required', 'in:publish,reject'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $results = $moderation->bulkModerate($request->user(), $data['reviewIds'], $data['action'], $data['reason'] ?? null);

        return response()->json(['data' => ['results' => $results]]);
    }

    private function reviewPayload(ProductReview $review): array
    {
        return [
            'id' => $review->id,
            'productId' => $review->product_id,
            'productTitle' => $review->product_title_snapshot,
            'productThumbnail' => $review->product?->images->sortBy('position')->first()?->path,
            'productHandle' => $review->product?->handle,
            'buyerName' => $review->customer_name_snapshot ?: 'Verified buyer',
            'customerId' => $review->customer_id,
            'customerEmail' => $review->customer?->email,
            'orderNumber' => $review->order_number_snapshot,
            'variantTitle' => $review->variant_title_snapshot,
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => $review->comment,
            'status' => $review->status,
            'moderationNote' => $review->moderation_reason,
            'moderatedBy' => $review->moderator?->name,
            'submittedAt' => $review->submitted_at?->toIso8601String(),
            'publishedAt' => $review->published_at?->toIso8601String(),
            'photos' => $review->media->map(fn ($media): array => [
                'id' => $media->id,
                'mimeType' => $media->mime_type,
                'url' => route('admin.api.reviews.media.show', $media),
            ])->values(),
        ];
    }
}
