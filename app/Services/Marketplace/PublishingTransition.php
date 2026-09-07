<?php

namespace App\Services\Marketplace;

/** Publishing is not a read-sync retry: ambiguous writes require reconciliation. */
final class PublishingTransition
{
    public static function validate(string $from, string $to, ?string $externalItemId = null): void
    {
        $allowed = [
            'queued' => ['processing', 'rejected'],
            'processing' => ['succeeded', 'rejected', 'uncertain'],
            // Only reconciliation may resolve ambiguity; never blindly send again.
            'uncertain' => ['succeeded', 'rejected'],
            'succeeded' => [],
            'rejected' => [],
        ];
        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new \DomainException('Invalid publishing transition; reconcile ambiguous results before creating a new attempt.');
        }
        if ($to === 'succeeded' && ($externalItemId === null || trim($externalItemId) === '')) {
            throw new \DomainException('A confirmed external item identity is required for publishing success.');
        }
    }
}
