<?php

namespace App\Services\Shipping;

use RuntimeException;

final class EasyParcelTrackingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('EasyParcel tracking synchronization failed.');
    }
}
