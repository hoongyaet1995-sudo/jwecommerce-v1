<?php

namespace Tests\Feature;

use App\Services\Marketing\{MarketingDraftContract, MarketingTextClient, MarketingWriter};
use App\Services\AI\ProviderFailure;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketingDraftContractTest extends TestCase
{
    private function draft(): array
    {
        $scenes = array_map(fn ($i) => ['visual'=>'Show the product and its uses.', 'voiceover'=>'Consider enclosed storage.', 'subtitle'=>'Enclosed storage', 'duration'=>5, 'material'=>'text-only original graphic', 'asset_ids'=>[]], range(1,6));
        return ['summary'=>'A practical use story.', 'duration'=>30, 'cta'=>'Request a quote', 'changes'=>[], 'requirements'=>[], 'scenes'=>$scenes,
            'production'=>['brand'=>['name'=>'ACME', 'descriptor'=>'Storage equipment'], 'scenes'=>array_map(fn ($layout)=>['layout'=>$layout,'heading'=>'Enclosed storage','lines'=>['Equipment','Materials','General goods'],'body'=>'Suitability depends on needs.'], ['need_open','product_intro','use_map','business_uses','requirements','cta_close'])]];
    }

    private function facts(string $mode='quote'): array
    {
        return ['creative_planning'=>true,'draft_contract'=>'scene-requirements-v2','snapshot'=>['purchase_mode'=>$mode], 'brief'=>['cta'=>'Request a quote'], 'assets'=>[]];
    }

    private function requirement(string $kind='quotation_note', int $scene=0, string $evidence=''): array
    {
        return ['kind'=>$kind, 'message'=>'Final specification, condition, availability and delivery are confirmed in the quotation.', 'scene_number'=>$scene, 'evidence'=>$evidence];
    }

    public function test_unused_quotation_boundary_is_visible_note_not_required_gap(): void
    {
        $draft=$this->draft();$draft['requirements']=[$this->requirement()];
        $result=app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts());
        $this->assertSame([], $result['gaps']);
        $this->assertSame([$draft['requirements'][0]['message']], $result['notes']);
        $this->assertSame($draft['scenes'], $result['scenes']);
        $this->assertSame($draft['requirements'], $result['requirements']);
    }

    public function test_scene_linked_quote_claim_and_permission_remain_blocking(): void
    {
        $draft=$this->draft();$draft['scenes'][1]['voiceover']='Available for immediate delivery.';
        $draft['requirements']=[$this->requirement('quotation_note',2,'Available for immediate delivery.'),$this->requirement('material_permission',1,'Show the product')];
        $result=app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts());
        $this->assertCount(2,$result['gaps']);$this->assertSame([],$result['notes']);
    }

    public function test_missing_or_invented_scene_evidence_never_silently_clears_requirement(): void
    {
        $draft=$this->draft();$draft['requirements']=[$this->requirement('required_fact',2,'Invented scene text')];
        $this->expectException(ValidationException::class);
        app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts());
    }

    public function test_non_quote_source_cannot_downgrade_a_requirement_to_quotation_note(): void
    {
        $draft=$this->draft();$draft['requirements']=[$this->requirement()];
        $result=app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts('buy'));
        $this->assertCount(1,$result['gaps']);$this->assertSame([],$result['notes']);
    }

    public function test_malformed_scene_returns_a_validation_error_without_clearing_requirement(): void
    {
        $draft=$this->draft();$draft['scenes'][0]='malformed';$draft['requirements']=[$this->requirement('required_fact',1,'product')];
        $this->expectException(ValidationException::class);
        app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts());
    }

    public function test_free_text_legacy_gaps_are_not_accepted_as_v2_requirements(): void
    {
        $draft=$this->draft();unset($draft['requirements']);$draft['gaps']=['Some unknown claim'];
        $this->expectException(ValidationException::class);
        app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts());
    }

    public function test_strict_schema_reaches_provider_with_real_display_limits(): void
    {
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>'{}']]]]])]);
        $schema=app(MarketingDraftContract::class)->storyboardSchema();
        app(MarketingTextClient::class)->text('fake-key','gpt-5.6-luna','Test contract',$schema);
        Http::assertSent(fn($r)=>$r['text']['format']['type']==='json_schema' && $r['text']['format']['strict']===true && $r['text']['format']['schema']['properties']['production']['properties']['brand']['properties']['descriptor']['maxLength']===60 && $r['text']['format']['schema']['properties']['production']['properties']['scenes']['items']['properties']['lines']['items']['maxLength']===24 && $r['store']===false);
        Http::assertSentCount(1);
    }

    public function test_unsupported_schema_never_retries_as_unconstrained_json(): void
    {
        Http::fake(['api.openai.com/*'=>Http::response(['error'=>['message'=>'Unsupported schema']],400)]);
        try { app(MarketingTextClient::class)->text('fake-key','gpt-5.6-luna','Test',app(MarketingDraftContract::class)->storyboardSchema());$this->fail('Expected provider rejection'); }
        catch(ProviderFailure $e) { Http::assertSentCount(1); }
    }

    public function test_refusal_cannot_be_mixed_with_output_text_as_a_valid_draft(): void
    {
        Http::fake(['api.openai.com/*'=>Http::response(['status'=>'completed','output'=>[['content'=>[['type'=>'refusal','refusal'=>'Not generated'],['type'=>'output_text','text'=>'{}']]]]])]);
        $this->expectException(ValidationException::class);
        app(MarketingTextClient::class)->text('fake-key','gpt-5.6-luna','Test');
    }

    public function test_overlength_display_copy_still_rejected_locally_without_truncation(): void
    {
        $draft=$this->draft();$draft['production']['brand']['descriptor']=str_repeat('x',61);
        $this->expectException(ValidationException::class);
        app(MarketingWriter::class)->validateOutput($draft,'storyboard',$this->facts());
    }
}
