<?php

namespace App\Domain\Reviews;

use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductReviewModerationService
{
    public function moderate(User $actor, ProductReview $review, string $action, ?string $reason = null, ?string $batchId = null): ProductReview
    {
        if (! $actor->can('moderate', $review)) {
            throw new AuthorizationException();
        }

        return DB::transaction(function () use ($actor, $review, $action, $reason, $batchId): ProductReview {
            $locked = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
            $from = $locked->status;
            $transition = match ($action) {
                'publish' => $from === 'pending' ? ['published', 'published'] : null,
                'reject' => in_array($from, ['pending', 'published'], true) ? ['rejected', $from === 'published' ? 'withdrawn' : 'rejected'] : null,
                default => null,
            };
            if ($transition === null) {
                throw ValidationException::withMessages(['action' => ['This review cannot make the requested transition.']]);
            }

            [$to, $event] = $transition;
            $locked->update([
                'status' => $to,
                'active_lock' => $to === 'rejected' ? null : 'active',
                'moderation_reason' => $reason,
                'moderated_by' => $actor->id,
                'published_at' => $to === 'published' ? now() : null,
            ]);
            $locked->events()->create([
                'actor_user_id' => $actor->id,
                'event_type' => $event,
                'from_status' => $from,
                'to_status' => $to,
                'batch_id' => $batchId,
                'metadata' => $reason === null ? [] : ['reason' => $reason],
                'occurred_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    /** @return list<array{reviewId: int, ok: bool, status?: string, reason?: string}> */
    public function bulkModerate(User $actor, array $reviewIds, string $action, ?string $reason = null): array
    {
        if (! $actor->can('bulkModerate', ProductReview::class)) {
            throw new AuthorizationException();
        }
        $batchId = (string) Str::uuid();

        return array_map(function (int|string $reviewId) use ($actor, $action, $reason, $batchId): array {
            try {
                $review = ProductReview::query()->findOrFail($reviewId);
                $updated = $this->moderate($actor, $review, $action, $reason, $batchId);
                return ['reviewId' => $updated->id, 'ok' => true, 'status' => $updated->status];
            } catch (\Throwable $exception) {
                return ['reviewId' => (int) $reviewId, 'ok' => false, 'reason' => $exception->getMessage()];
            }
        }, $reviewIds);
    }
}
