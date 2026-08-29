<?php

namespace App\Services\Shipping;

use RuntimeException;

final class EasyParcelReconciliationPendingException extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct('EasyParcel reconciliation is still pending.');
    }
}
