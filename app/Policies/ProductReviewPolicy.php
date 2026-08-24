<?php

namespace App\Policies;

use App\Domain\Reviews\ReviewPermission;
use App\Models\ProductReview;
use App\Models\User;

class ProductReviewPolicy
{
    public function view(User $user, ProductReview $review): bool
    {
        return $review->customer?->user_id === $user->id || ReviewPermission::allows($user, ReviewPermission::VIEW);
    }

    public function moderate(User $user, ProductReview $review): bool
    {
        return ReviewPermission::allows($user, ReviewPermission::MODERATE);
    }

    public function bulkModerate(User $user): bool
    {
        return ReviewPermission::allows($user, ReviewPermission::BULK_MODERATE);
    }

    public function viewAudit(User $user, ProductReview $review): bool
    {
        return ReviewPermission::allows($user, ReviewPermission::AUDIT_VIEW);
    }

    public function viewPrivateMedia(User $user, ProductReview $review): bool
    {
        return $review->customer?->user_id === $user->id || ReviewPermission::allows($user, ReviewPermission::VIEW);
    }
}
