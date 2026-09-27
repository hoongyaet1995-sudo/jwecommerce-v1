<?php

namespace Tests\Unit;

use App\Services\Marketplace\PublishingFailureDetails;
use App\Services\Marketplace\PublishingResultUncertain;
use App\Services\Marketplace\PublishingProviderRejected;
use PHPUnit\Framework\TestCase;

class PublishingFailureDetailsTest extends TestCase
{
    public function test_uncertain_provider_diagnostics_are_preserved_for_staff_review(): void
    {
        $error=new PublishingResultUncertain('Lazada product creation was not confirmed.', [
            'http_status'=>200,
            'provider_code'=>'500',
            'request_id'=>'0babf4c817898893301095383',
            'unsafe'=>'discard-me',
        ]);

        $details=PublishingFailureDetails::fromThrowable($error,'create');

        $this->assertSame('provider_result_unknown',$details['code']);
        $this->assertSame('create',$details['stage']);
        $this->assertSame([
            'http_status'=>200,
            'provider_code'=>'500',
            'request_id'=>'0babf4c817898893301095383',
        ],$details['diagnostics']);
    }

    public function test_explicit_provider_rejection_keeps_bounded_diagnostics(): void
    {
        $details=PublishingFailureDetails::fromThrowable(new PublishingProviderRejected('Rejected',[
            'http_status'=>200,'provider_code'=>'500','request_id'=>'2141336017898901754267490','provider_message'=>'Business validation failed',
        ]),'create');

        $this->assertSame(['http_status'=>200,'provider_code'=>'500','request_id'=>'2141336017898901754267490',
            'provider_message'=>'Business validation failed'],$details['diagnostics']);
    }
}
