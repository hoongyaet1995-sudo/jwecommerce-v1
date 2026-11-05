<?php
namespace App\Services\Reports;

/** A later refund state does not erase an immutable, dated original receipt. */
final class PaymentEvidence
{
    public const CONFIRMED_STATUSES=['paid','captured','succeeded','partially_refunded','refunded'];
}
