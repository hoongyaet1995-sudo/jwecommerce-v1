<?php

namespace App\Domain\Notifications;

final readonly class RenderedNotification
{
    /** @param array<string, string> $variables */
    public function __construct(
        public string $subject,
        public string $preheader,
        public string $heading,
        public string $body,
        public string $ctaText,
        public string $ctaUrl,
        public array $variables,
    ) {}
}
