<?php

namespace App\Domain\Payments;

use App\Models\User;

final class PaymentProofPermission
{
    public const VIEW = 'payments.proof.view';
    public const REVIEW = 'payments.proof.review';

    public const ALL = [self::VIEW, self::REVIEW];

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
