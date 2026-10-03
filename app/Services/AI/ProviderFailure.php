<?php

namespace App\Services\AI;

use RuntimeException;

class ProviderFailure extends RuntimeException
{
    // Safe codes only. Never retain the raw provider body, key or HTTP exception.
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'temperature' => 'The selected model does not support custom temperature. Select a compatible model.',
            'authentication' => 'The provider rejected this API key. Check the key and its permissions.',
            'quota' => 'The provider reported a quota, billing or rate limit. Check your provider account.',
            'endpoint' => 'The selected API endpoint is not allowed or is unavailable.',
            'models' => 'The provider did not return a usable model list.',
            'response' => 'The selected model did not return a text response.',
            default => 'The AI provider could not complete the request. Please try again later.',
        });
    }
}
