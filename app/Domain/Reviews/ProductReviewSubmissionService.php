<?php

namespace App\Domain\Reviews;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductReviewSubmissionService
{
    public function __construct(
        private readonly ProductReviewEligibilityService $eligibility,
        private readonly ProductReviewMediaService $media,
    ) {}

    /** @param array{rating: int, title: string, comment: string} $payload @param list<UploadedFile> $files */
    public function submit(Customer $customer, int $orderItemId, array $payload, array $files = []): ProductReview
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($customer, $orderItemId, $payload, $files, &$storedPaths): ProductReview {
                $item = OrderItem::query()->lockForUpdate()->with(['order', 'productVariant.product', 'reviews'])->findOrFail($orderItemId);
                $this->eligibility->assertCanSubmit($customer, $item);
                $variant = $item->productVariant;
                $product = $variant?->product;
                if ($variant === null || $product === null) {
                    throw new RuntimeException('The delivered order item cannot be mapped to an active catalog product.');
                }

                $attemptNumber = ((int) $item->reviews()->max('attempt_number')) + 1;
                $review = ProductReview::query()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $product->id,
                    'customer_id' => $customer->id,
                    'attempt_number' => $attemptNumber,
                    'status' => 'pending',
                    'active_lock' => 'active',
                    'rating' => $payload['rating'],
                    'title' => $payload['title'],
                    'comment' => $payload['comment'],
                    'order_number_snapshot' => $item->order->number,
                    'product_title_snapshot' => $product->title,
                    'variant_title_snapshot' => $item->variant_title,
                    'customer_name_snapshot' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: null,
                    'submitted_at' => now(),
                ]);

                foreach ($files as $position => $file) {
                    $stored = $this->media->storePending($review, $file, $position);
                    $storedPaths[] = $stored->original_path;
                    $this->event($review, 'media_attached', $customer->user_id, ['mediaId' => $stored->id]);
                }
                $this->event($review, 'submitted', $customer->user_id, ['attemptNumber' => $attemptNumber]);

                return $review->load('media');
            });
        } catch (\Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('review-private')->delete($storedPaths);
            }
            throw $exception;
        }
    }

    private function event(ProductReview $review, string $type, ?int $actorUserId, array $metadata): void
    {
        $review->events()->create([
            'actor_user_id' => $actorUserId,
            'event_type' => $type,
            'to_status' => $review->status,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
