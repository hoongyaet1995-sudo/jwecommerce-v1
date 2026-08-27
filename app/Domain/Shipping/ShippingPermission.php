<?php

namespace App\Domain\Shipping;

use App\Models\User;

final class ShippingPermission
{
    public const VIEW = 'shipping.view';
    public const DISPATCH = 'shipping.dispatch';
    public const UPDATE_STATUS = 'shipping.update_status';

    public const ALL = [self::VIEW, self::DISPATCH, self::UPDATE_STATUS];

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
