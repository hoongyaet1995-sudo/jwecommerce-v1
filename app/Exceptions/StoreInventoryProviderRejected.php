<?php

namespace App\Exceptions;

use RuntimeException;

/** Bounded provider diagnostics; credentials and raw responses are never persisted. */
final class StoreInventoryProviderRejected extends RuntimeException
{
    public readonly ?string $providerCode;
    public readonly ?string $providerMessage;

    public function __construct(public readonly int $httpStatus, mixed $code, mixed $message)
    {
        $this->providerCode = (is_string($code) || is_int($code))
            && preg_match('/\A[A-Za-z0-9._-]{1,80}\z/D', (string) $code) ? (string) $code : null;
        $safeMessage = is_string($message) ? trim($message) : '';
        $safeMessage = preg_replace('/[A-Za-z0-9_-]{24,}/', '[redacted]', $safeMessage);
        $this->providerMessage = $safeMessage !== ''
            && preg_match('/\A[A-Za-z0-9 .,;:()_\/-]{1,180}\z/D', $safeMessage) ? $safeMessage : null;
        parent::__construct('Lazada rejected the facility stock update'
            .($this->providerCode ? ' ('.$this->providerCode.')' : '')
            .($this->providerMessage ? ': '.$this->providerMessage : '.'));
    }
}
