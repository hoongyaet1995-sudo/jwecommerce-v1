<?php

namespace App\Domain\Notifications;

use RuntimeException;

final class NotificationDeliveryException extends RuntimeException
{
    private const ALLOWED_CODES = [
        'queue',
        'transport',
        'timeout',
        'unknown',
    ];

    public readonly string $safeCode;

    public function __construct(string $safeCode)
    {
        $this->safeCode = in_array($safeCode, self::ALLOWED_CODES, true)
            ? $safeCode
            : 'unknown';

        parent::__construct("Order notification delivery failed [{$this->safeCode}].");
    }
}
