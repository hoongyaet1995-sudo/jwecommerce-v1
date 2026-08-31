<?php

namespace App\Domain\Notifications;

use App\Models\User;

class NotificationPermission
{
    public const TEMPLATES_VIEW = 'notifications.templates.view';

    public const TEMPLATES_MANAGE = 'notifications.templates.manage';

    public const TEST_SEND = 'notifications.test.send';

    public const DELIVERIES_VIEW = 'notifications.deliveries.view';

    public const DELIVERIES_RETRY = 'notifications.deliveries.retry';

    public const DELIVERIES_RESEND = 'notifications.deliveries.resend';

    public const CURRENT_SEND = 'notifications.current.send';

    public const ALL = [
        self::TEMPLATES_VIEW,
        self::TEMPLATES_MANAGE,
        self::TEST_SEND,
        self::DELIVERIES_VIEW,
        self::DELIVERIES_RETRY,
        self::DELIVERIES_RESEND,
        self::CURRENT_SEND,
    ];

    public static function allows(User $user, string $permission): bool
    {
        return $user->is_super_admin
            || $user->permissions()->where('permission', $permission)->exists();
    }
}
