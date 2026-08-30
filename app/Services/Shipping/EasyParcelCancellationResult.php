<?php

namespace App\Services\Shipping;

final readonly class EasyParcelCancellationResult
{
    public function __construct(
        public string $state,
        public string $message,
    ) {}
}
