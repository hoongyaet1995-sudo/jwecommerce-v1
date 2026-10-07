<?php
namespace Tests\Unit;
use Tests\TestCase;
use App\Services\Support\SupportReplySegmenter;
class SupportReplySegmenterTest extends TestCase
{
    public function test_lossless_boundaries_and_fallback_keep_conditions_and_urls():void {
        $s=app(SupportReplySegmenter::class);$answer="RM8 within Peninsular Malaysia.\n\n14 days, unused, original packaging.";
        $this->assertSame(explode("\n\n",$answer),$s->segments($answer,explode("\n\n",$answer),3,2000));
        $this->assertSame([$answer],$s->segments($answer,['RM8','14 days'],3,2000));
        $this->assertSame([$answer],$s->segments($answer,explode("\n\n",$answer),1,2000));
        $this->assertSame(['Short answer.'],$s->segments('Short answer.',['Short answer.'],3,2000));
        $this->assertSame(['https://example.test/track'],$s->segments('https://example.test/track',['https://example.test','/track'],3,2000));
    }
    public function test_oversized_answer_fails_instead_of_truncating():void {
        $this->expectException(\LengthException::class);
        app(SupportReplySegmenter::class)->segments(str_repeat('x',2001),[],3,2000);
    }
}
