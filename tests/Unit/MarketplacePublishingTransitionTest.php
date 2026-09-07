<?php

namespace Tests\Unit;

use App\Services\Marketplace\PublishingTransition;
use PHPUnit\Framework\TestCase;

class MarketplacePublishingTransitionTest extends TestCase
{
    public function test_uncertain_result_requires_reconciliation_before_resubmission(): void
    {
        $this->expectException(\DomainException::class);
        PublishingTransition::validate('uncertain', 'processing');
    }

    public function test_success_requires_real_external_identity_even_after_reconciliation(): void
    {
        foreach (['processing', 'uncertain'] as $state) {
            try {
                PublishingTransition::validate($state, 'succeeded');
                $this->fail('Success without item identity must fail.');
            } catch (\DomainException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            PublishingTransition::validate($state, 'succeeded', '846055463');
        }
    }

    public function test_completed_publish_cannot_be_requeued(): void
    {
        $this->expectException(\DomainException::class);
        PublishingTransition::validate('succeeded', 'queued');
    }

    public function test_queued_work_can_start_and_become_uncertain_without_claiming_success(): void
    {
        PublishingTransition::validate('queued', 'processing');
        PublishingTransition::validate('processing', 'uncertain');
        $this->expectException(\DomainException::class);
        PublishingTransition::validate('queued', 'succeeded', '846055463');
    }
}
