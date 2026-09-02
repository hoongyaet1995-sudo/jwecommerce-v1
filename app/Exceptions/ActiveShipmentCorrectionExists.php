<?php

namespace App\Exceptions;

use RuntimeException;

class ActiveShipmentCorrectionExists extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An active fulfilment correction already exists for this order.');
    }
}
