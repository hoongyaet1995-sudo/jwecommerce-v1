<?php
namespace Tests\Feature;

use App\Services\Support\ProductRequirements;
use App\Services\Support\SupportGenerationContract;
use Tests\TestCase;

class SupportProductConstraintsTest extends TestCase
{
    public function test_alternative_or_negative_measurements_are_not_positive_requirements(): void
    {
        $service=app(ProductRequirements::class);
        $this->assertSame([], $service->fromQuery('16 GB or 32 GB')['required']);
        $this->assertSame([], $service->fromQuery('not 16 GB')['required']);
    }
    public function test_measurements_are_exact_and_cross_industry(): void
    {
        $service = app(ProductRequirements::class);
        foreach ([['16 GB', 'RAM 16GB', 'RAM 116GB'], ['27 inch', 'Monitor 27-inch', 'Monitor 127 inch'], ['500 ml', 'Bottle 500ml', 'Bottle 1500ml']] as [$query, $yes, $no]) {
            $constraints = $service->fromQuery($query);
            $this->assertTrue($service->matches($yes, $constraints));
            $this->assertFalse($service->matches($no, $constraints));
        }
    }

    public function test_literal_attributes_and_exclusions_do_not_invent_compatibility(): void
    {
        $service = app(ProductRequirements::class);
        $requirements = ['required'=>['DDR4', '16 GB'], 'excluded'=>['SO-DIMM']];
        $this->assertTrue($service->matches('RAM DDR4 16GB desktop', $requirements));
        $this->assertFalse($service->matches('RAM DDR5 16GB desktop', $requirements));
        $this->assertFalse($service->matches('RAM DDR4 16GB SO-DIMM', $requirements));
        $this->assertTrue($service->matches('Cotton shirt size XL', ['required'=>['XL'], 'excluded'=>[]]));
        $this->assertFalse($service->matches('Cotton shirt size XXL', ['required'=>['XL'], 'excluded'=>[]]));
    }

    public function test_generation_budget_and_invalid_json_have_specific_safe_diagnosis(): void
    {
        $contract = app(SupportGenerationContract::class);
        $this->assertGreaterThan(250, $contract->budget(250));
        $this->assertSame('Hello', $contract->decode('{"answer":"Hello","faq_ids":[]}')['answer']);
        $this->expectExceptionMessage('invalid_answer_json');
        $contract->decode('{"answer":"cut off');
    }

    public function test_provider_incomplete_output_is_not_returned_as_a_valid_answer(): void
    {
        $transport=$this->mock(\App\Services\AI\ProviderTransport::class);
        $transport->shouldReceive('json')->once()->andReturn(['status'=>'incomplete','incomplete_details'=>['reason'=>'max_output_tokens'],'output'=>[['content'=>[['type'=>'output_text','text'=>'partial']]]]]);
        try { app(\App\Services\AI\OpenAiClient::class)->text('fake',null,'fixture','test',1200); $this->fail('Incomplete output must fail.'); }
        catch (\App\Services\AI\ProviderFailure $error) { $this->assertSame('provider_output_limit', app(SupportGenerationContract::class)->diagnosis($error)); }
    }
}
