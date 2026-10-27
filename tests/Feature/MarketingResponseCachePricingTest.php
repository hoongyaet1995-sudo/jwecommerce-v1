<?php

namespace Tests\Feature;

use App\Services\Marketing\MarketingStoryboardOpenAi;
use Tests\TestCase;

class MarketingResponseCachePricingTest extends TestCase
{
    private function rates(): array
    {
        return ['input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2,'cached_usd_per_million'=>0.02,'cache_write_usd_per_million'=>0.25,'cache_write_12h_usd_per_million'=>0.5];
    }

    public function test_real_luna_receipt_includes_write_rate_without_double_counting(): void
    {
        $usage=['input_tokens'=>4648,'input_tokens_details'=>['cached_tokens'=>0,'cache_write_tokens'=>4645],'output_tokens'=>284,'total_tokens'=>4932];
        $this->assertSame(1503,app(MarketingStoryboardOpenAi::class)->cost($usage,$this->rates()));
        $this->assertNull(app(MarketingStoryboardOpenAi::class)->cost($usage,['input_usd_per_million'=>0.2,'output_usd_per_million'=>1.2]));
    }

    public function test_mixed_cache_lifetimes_and_reads_are_charged_once(): void
    {
        $usage=['input_tokens'=>12000,'input_tokens_details'=>['cached_tokens'=>4000,'cache_write_tokens'=>7000,'cache_write_tokens_details'=>['ttl_30m_tokens'=>3000,'ttl_12h_tokens'=>4000]],'output_tokens'=>100,'total_tokens'=>12100];
        $this->assertSame(3150,app(MarketingStoryboardOpenAi::class)->cost($usage,$this->rates()));
        $usage['input_tokens_details']['cache_write_tokens']=13000;
        $this->assertNull(app(MarketingStoryboardOpenAi::class)->cost($usage,$this->rates()));
    }

    public function test_missing_breakdown_rate_and_invalid_counts_pause_costing(): void
    {
        $usage=['input_tokens'=>1000,'output_tokens'=>100,'input_tokens_details'=>['cached_tokens'=>100,'cache_write_tokens'=>200]];
        $cfg=$this->rates();unset($cfg['cached_usd_per_million']);
        $this->assertNull(app(MarketingStoryboardOpenAi::class)->cost($usage,$cfg));
        $usage['input_tokens_details']['cached_tokens']=-1;
        $this->assertNull(app(MarketingStoryboardOpenAi::class)->cost($usage,$this->rates()));
        $this->assertSame(320,app(MarketingStoryboardOpenAi::class)->cost(['input_tokens'=>1000,'output_tokens'=>100],$this->rates()));
    }
}
