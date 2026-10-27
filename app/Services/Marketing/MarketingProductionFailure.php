<?php

namespace App\Services\Marketing;

class MarketingProductionFailure extends \RuntimeException
{
    public function __construct(public string $reason, public string $state = 'failed')
    {
        parent::__construct($reason);
    }
}
