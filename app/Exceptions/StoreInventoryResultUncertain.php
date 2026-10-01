<?php

namespace App\Exceptions;

use RuntimeException;

final class StoreInventoryResultUncertain extends RuntimeException
{
    public readonly array $diagnostics;

    public function __construct(string $message, array $diagnostics = [])
    {
        parent::__construct($message);
        $this->diagnostics = array_intersect_key($diagnostics, array_flip([
            'write_accepted', 'write_request_id', 'write_accepted_at', 'requested_quantity',
            'observed_quantity', 'provider_location_id', 'provider_shop_id', 'readback_checked_at',
        ]));
    }
}
