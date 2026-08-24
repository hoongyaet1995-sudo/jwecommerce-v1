<?php

namespace App\Domain\Reviews;

use App\Models\User;

final class ReviewPermission
{
    public const VIEW = 'reviews.view';
    public const MODERATE = 'reviews.moderate';
    public const BULK_MODERATE = 'reviews.bulk_moderate';
    public const AUDIT_VIEW = 'reviews.audit.view';

    public const ALL = [self::VIEW, self::MODERATE, self::BULK_MODERATE, self::AUDIT_VIEW];

    public static function allows(User $user, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $user->is_admin
            && in_array($permission, self::ALL, true)
            && $user->permissions()->where('permission', $permission)->exists();
    }
}
