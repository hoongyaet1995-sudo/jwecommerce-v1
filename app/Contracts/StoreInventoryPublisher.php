<?php

namespace App\Contracts;

interface StoreInventoryPublisher
{
    /** @return array<string,mixed> Sanitized provider receipt without credentials. */
    public function publish(object $store, object $listing, int $quantity, string $eventKey): array;
}
