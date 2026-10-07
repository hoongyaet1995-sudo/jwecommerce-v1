<?php
namespace Tests\Feature;

use App\Models\SupportAttachmentAnalysis;
use App\Services\Support\SupportAttachmentAnalysisPolicy;
use Tests\TestCase;

class SupportAttachmentAnalysisTest extends TestCase
{
    public function test_analysis_defaults_off_and_limits_are_bounded(): void
    {
        $policy = new SupportAttachmentAnalysisPolicy();
        $defaults = $policy->settings([]);
        foreach (['image','document','audio'] as $kind) $this->assertFalse($defaults[$kind]);
        $this->assertSame(10, $defaults['pdfPages']);
        $this->assertSame(120, $defaults['audioSeconds']);
        $this->assertSame(30, $defaults['retentionDays']);
        $bounded = $policy->settings(['attachmentAnalysis'=>['pdfPages'=>100,'audioSeconds'=>0,'retentionDays'=>999]]);
        $this->assertSame(20,$bounded['pdfPages']);
        $this->assertSame(15,$bounded['audioSeconds']);
        $this->assertSame(90,$bounded['retentionDays']);
    }

    public function test_analysis_result_is_encrypted_and_not_serialized(): void
    {
        $analysis = new SupportAttachmentAnalysis();
        $analysis->result = ['transcript'=>'Private customer words'];
        $this->assertStringNotContainsString('Private customer words',$analysis->getAttributes()['result']);
        $this->assertSame('Private customer words',$analysis->result['transcript']);
        $this->assertArrayNotHasKey('result',$analysis->toArray());
    }
}
